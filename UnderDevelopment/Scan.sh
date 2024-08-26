#!/bin/bash

# 定義結果目錄
output_dir="fping_results"
prev_output_dir="fping_prev_results"
red_light_dir="fping_red_lights"

# 創建結果目錄
mkdir -p $output_dir $prev_output_dir $red_light_dir

# 設置目錄和文件擁有者和權限
chown -R apache:apache $output_dir $prev_output_dir $red_light_dir
chmod -R 755 $output_dir $prev_output_dir $red_light_dir

# 無限循環執行
while true; do
    echo "Starting scan at $(date)"
    
    # 動態生成子網清單
    declare -A subnets=()
    script_dir=$(dirname "$0")  # 获取脚本所在目录
    subnet_files=("$script_dir/subnets/"*.php)

    # 檢查是否有子網文件存在
    if [ -e "${subnet_files[0]}" ]; then
        for file in "${subnet_files[@]}"; do
            # 使用 PHP 提取數據
            category=$(php -r "\$arr = include('$file'); echo \$arr['category'];")
            ip=$(php -r "\$arr = include('$file'); echo \$arr['ip'];")
            mask=$(php -r "\$arr = include('$file'); echo \$arr['mask'];")
            subnets["$category"]="$ip/$mask"
        done

        # 逐個掃描網段 (並行執行)
        for label in "${!subnets[@]}"; do
            subnet=${subnets[$label]}
            {
                temp_file="$output_dir/${label}_temp_results.txt"
                output_file="$output_dir/${label}_results.txt"
                prev_output_file="$prev_output_dir/${label}_results.txt"
                red_light_file="$red_light_dir/${label}_red_light.txt"

                echo "Scanning subnet: $subnet"
                
                # 清空臨時文件
                > $temp_file
                
                # 將 /24 網段拆分為 /32（每個 IP）並逐一掃描
                nmap -n -sL $subnet | grep 'Nmap scan report for' | awk '{print $NF}' | while read ip; do
                    # 單個 IP 地址掃描
                    fping -c1 -t50 $ip 2>/dev/null && echo $ip >> $temp_file &
                done

                # 等待所有背景任務完成
                wait

                # 保存前一次的結果
                cp $output_file $prev_output_file 2>/dev/null

                # 將臨時文件的內容寫入主結果文件
                cat $temp_file > $output_file

                # 比較前後兩次的掃描結果
                if [ -f "$prev_output_file" ]; then
                    # 讀取之前和當前的結果
                    prev_ips=$(cat $prev_output_file)
                    current_ips=$(cat $output_file)

                    # 檢查之前可達但現在不可達的 IP
                    for ip in $prev_ips; do
                        if ! grep -q "$ip" <<< "$current_ips"; then
                            echo "$ip" >> $red_light_file
                        fi
                    done

                    # 去重並保存紅燈狀態
                    sort -u $red_light_file -o $red_light_file
                fi

                # 刪除臨時文件
                rm $temp_file
                # 設置文件擁有者和權限
                chown apache:apache $output_file $prev_output_file $red_light_file
                chmod 644 $output_file $prev_output_file $red_light_file
           } &
        done

        # 等待所有背景任務完成
        wait

        echo "Scan complete. Results saved in $output_dir."
    else
        echo "No subnet files found in subnets/. Sleeping for 10 seconds."
        sleep 10
    fi
done
