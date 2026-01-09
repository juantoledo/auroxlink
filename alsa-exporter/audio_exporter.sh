#!/bin/bash
# audio_exporter.sh - Exports ALSA PCM status to Prometheus node exporter format

METRICS_FILE="/var/lib/node_exporter/textfile_collector/audio_pcm.prom"
STATE_FILE="/var/lib/node_exporter/textfile_collector/.audio_pcm_state"
# Monitor multiple cards - space separated list (e.g., "0 1 2 3")
CARDS="0 1 2 3 4"

declare -A state_map=([closed]=0 [open]=1 [prepared]=2 [RUNNING]=3)
declare -A previous_state
declare -A state_change_counter
declare -A running_time_total

# Load previous state and counters
if [ -f "$STATE_FILE" ]; then
    source "$STATE_FILE"
fi

while true; do
    {
        echo "# HELP alsa_pcm_state ALSA PCM device state (0=closed, 1=open, 2=prepared, 3=RUNNING)"
        echo "# TYPE alsa_pcm_state gauge"
        
        echo "# HELP alsa_pcm_state_changes_total Total number of state changes"
        echo "# TYPE alsa_pcm_state_changes_total counter"
        
        echo "# HELP alsa_pcm_running_seconds_total Total seconds in RUNNING state"
        echo "# TYPE alsa_pcm_running_seconds_total counter"
        
        # Loop through all specified cards
        for CARD in $CARDS; do
            # Check if card exists
            if [ ! -d "/proc/asound/card${CARD}" ]; then
                continue
            fi
            
            # Scan all PCM devices for this card
            for f in /proc/asound/card${CARD}/pcm*/sub*/status; do
                if [ -f "$f" ]; then
                    pcm=$(echo $f | grep -oP 'pcm\d+[cp]')
                    sub=$(echo $f | grep -oP 'sub\d+')
                    type=$(echo $pcm | grep -oP '[cp]$')
                    [ "$type" = "p" ] && type="playback" || type="capture"
                    
                    # Create unique key for this device
                    device_key="${CARD}_${pcm}_${sub}"
                    
                    # Read the status file - it can be either just "state" or "state: value" format
                    state=$(cat "$f" | awk '/state:/{print $2}')
                    if [ -z "$state" ]; then
                        # If no "state:" prefix, read the first line directly
                        state=$(head -n1 "$f" | tr -d '[:space:]')
                    fi
                    state_val=${state_map[$state]:-0}
                    
                    # Track state changes
                    prev="${previous_state[$device_key]:-closed}"
                    if [ "$state" != "$prev" ]; then
                        state_change_counter[$device_key]=$((${state_change_counter[$device_key]:-0} + 1))
                        previous_state[$device_key]="$state"
                    fi
                    
                    # Track time in RUNNING state
                    if [ "$state" = "RUNNING" ]; then
                        running_time_total[$device_key]=$((${running_time_total[$device_key]:-0} + 1))
                    fi
                    
                    owner=$(awk '/owner_pid:/{print $2}' $f)
                    
                    # Export current state
                    echo "alsa_pcm_state{card=\"${CARD}\",device=\"${pcm}\",subdevice=\"${sub}\",type=\"${type}\"} ${state_val}"
                    
                    # Export state change counter
                    echo "alsa_pcm_state_changes_total{card=\"${CARD}\",device=\"${pcm}\",subdevice=\"${sub}\",type=\"${type}\"} ${state_change_counter[$device_key]:-0}"
                    
                    # Export running time counter
                    echo "alsa_pcm_running_seconds_total{card=\"${CARD}\",device=\"${pcm}\",subdevice=\"${sub}\",type=\"${type}\"} ${running_time_total[$device_key]:-0}"
                    
                    if [ -n "$owner" ]; then
                        echo "alsa_pcm_owner_pid{card=\"${CARD}\",device=\"${pcm}\",subdevice=\"${sub}\"} ${owner}"
                    fi
                fi
            done
        done
        
        echo "# HELP alsa_pcm_scrape_timestamp_seconds Unix timestamp of last scrape"
        echo "# TYPE alsa_pcm_scrape_timestamp_seconds gauge"
        echo "alsa_pcm_scrape_timestamp_seconds $(date +%s)"
        
    } > "${METRICS_FILE}.tmp"
    
    mv "${METRICS_FILE}.tmp" "$METRICS_FILE"
    
    # Save state for next iteration
    {
        for key in "${!previous_state[@]}"; do
            echo "previous_state[$key]=\"${previous_state[$key]}\""
        done
        for key in "${!state_change_counter[@]}"; do
            echo "state_change_counter[$key]=${state_change_counter[$key]}"
        done
        for key in "${!running_time_total[@]}"; do
            echo "running_time_total[$key]=${running_time_total[$key]}"
        done
    } > "$STATE_FILE"
    
    sleep 1
done
