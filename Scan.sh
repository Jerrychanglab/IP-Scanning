#!/bin/bash

# 定義要掃描的網段和標籤
declare -A subnets=(
    ["BORDER"]="10.31.32.0/24"
    ["MONITOR"]="10.31.33.0/24"
    ["ESXI"]="10.31.34.0/24"
    ["DB"]="10.31.35.0/24"
    ["API"]="10.31.36.0/24"
    ["WEB"]="10.31.37.0/24"
    ["AUTH"]="10.31.38.0/24"
    ["LOG"]="10.31.39.0/24"
    ["SHAPE"]="10.31.40.0/24"
    ["PDNS"]="10.31.41.0/24"
)

# 創建結果目錄
output_dir="fping_results"
prev_output_dir="fping_prev_results"
red_light_dir="fping_red_lights"

mkdir -p $output_dir $prev_output_dir $red_light_dir
# 設置目錄和文件擁有者和權限
chown -R apache:apache $output_dir $prev_output_dir $red_light_dir
chmod -R 755 $output_dir $prev_output_dir $red_light_dir

# 無限循環執行
while true; do
    echo "Starting scan at $(date)"
    # 逐個掃描網段 (並行執行)
    for label in "${!subnets[@]}"; do
        subnet=${subnets[$label]}
        {
            temp_file="$output_dir/${label}_temp_results.txt"
            output_file="$output_dir/${label}_results.txt"
            prev_output_file="$prev_output_dir/${label}_results.txt"
            red_light_file="$red_light_dir/${label}_red_light.txt"

            echo "Scanning subnet: $subnet"
            {
                echo "============================="
                echo "[$label] - $subnet"
                echo "============================="
                # 使用 fping 掃描網段，結果寫入暫存檔
                fping -a -g $subnet 2>/dev/null
                echo ""
            } > $temp_file

            # 保存前一次的結果
            cp $output_file $prev_output_file 2>/dev/null

            # 將暫存檔的內容寫入主結果檔
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

            # 刪除暫存檔
            rm $temp_file
            # 設置文件擁有者和權限
            chown apache:apache $output_file $prev_output_file $red_light_file
            chmod 644 $output_file $prev_output_file $red_light_file
       } &
    done

    # 等待所有背景任務完成
    wait

    echo "Scan complete. Results saved in $output_dir."
done
