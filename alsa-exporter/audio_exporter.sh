#!/bin/bash
# audio_exporter.sh - Exports ALSA PCM status to Prometheus node exporter format

METRICS_FILE="/var/lib/node_exporter/textfile_collector/audio_pcm.prom"
CARD=1

declare -A state_map=([closed]=0 [open]=1 [prepared]=2 [RUNNING]=3)

while true; do
    {
        echo "# HELP alsa_pcm_state ALSA PCM device state (0=closed, 1=open, 2=prepared, 3=RUNNING)"
        echo "# TYPE alsa_pcm_state gauge"
        
        for f in /proc/asound/card${CARD}/pcm*/sub*/status; do
            if [ -f "$f" ]; then
                pcm=$(echo $f | grep -oP 'pcm\d+[cp]')
                sub=$(echo $f | grep -oP 'sub\d+')
                type=$(echo $pcm | grep -oP '[cp]$')
                [ "$type" = "p" ] && type="playback" || type="capture"
                
                # Read the status file - it can be either just "state" or "state: value" format
                state=$(cat "$f" | awk '/state:/{print $2}')
                if [ -z "$state" ]; then
                    # If no "state:" prefix, read the first line directly
                    state=$(head -n1 "$f" | tr -d '[:space:]')
                fi
                state_val=${state_map[$state]:-0}
                
                owner=$(awk '/owner_pid:/{print $2}' $f)
                
                echo "alsa_pcm_state{card=\"${CARD}\",device=\"${pcm}\",subdevice=\"${sub}\",type=\"${type}\"} ${state_val}"
                
                if [ -n "$owner" ]; then
                    echo "alsa_pcm_owner_pid{card=\"${CARD}\",device=\"${pcm}\",subdevice=\"${sub}\"} ${owner}"
                fi
            fi
        done
        
        echo "# HELP alsa_pcm_scrape_timestamp_seconds Unix timestamp of last scrape"
        echo "# TYPE alsa_pcm_scrape_timestamp_seconds gauge"
        echo "alsa_pcm_scrape_timestamp_seconds $(date +%s)"
        
    } > "${METRICS_FILE}.tmp"
    
    mv "${METRICS_FILE}.tmp" "$METRICS_FILE"
    sleep 1
done
