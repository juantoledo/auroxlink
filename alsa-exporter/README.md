# ALSA Audio PCM State Exporter

Prometheus exporter for ALSA audio device states. Monitors PCM device status and exports metrics in Prometheus node_exporter textfile collector format.

## Features

- Real-time monitoring of ALSA PCM device states
- Tracks playback and capture devices
- Exports owner PID for active audio streams
- Compatible with Prometheus node_exporter
- Lightweight and efficient (1-second update interval)

## Metrics

### `alsa_pcm_state`
Current state of ALSA PCM devices:
- `0` = closed
- `1` = open
- `2` = prepared
- `3` = RUNNING

Labels:
- `card`: Sound card number
- `device`: PCM device (e.g., pcm0p, pcm1c)
- `subdevice`: Subdevice number
- `type`: playback or capture

### `alsa_pcm_owner_pid`
Process ID of the application using the audio device (when active).

### `alsa_pcm_scrape_timestamp_seconds`
Unix timestamp of the last metrics update.

## Requirements

- Linux system with ALSA
- Prometheus node_exporter with textfile collector enabled
- Root access for installation

## Installation

```bash
cd alsa-exporter
sudo ./install.sh
```

The installer will:
1. Create the textfile collector directory if needed
2. Copy the exporter script to `/usr/local/bin/`
3. Install and enable the systemd service
4. Start the service automatically

## Configuration

Edit `/usr/local/bin/audio_exporter.sh` to change:
- `CARD=1` - Change to your sound card number
- `METRICS_FILE` - Change metrics file location
- `sleep 1` - Adjust scraping interval

After changes, restart the service:
```bash
sudo systemctl restart alsa-exporter
```

## Usage

### View metrics
```bash
cat /var/lib/node_exporter/textfile_collector/audio_pcm.prom
```

### Service management
```bash
# Check status
sudo systemctl status alsa-exporter

# View logs
sudo journalctl -u alsa-exporter -f

# Restart
sudo systemctl restart alsa-exporter

# Stop
sudo systemctl stop alsa-exporter

# Start
sudo systemctl start alsa-exporter
```

## Uninstallation

```bash
cd alsa-exporter
sudo ./uninstall.sh
```

## Prometheus Configuration

Ensure node_exporter is started with textfile collector enabled:
```bash
node_exporter --collector.textfile.directory=/var/lib/node_exporter/textfile_collector
```

## Example Queries

### Check if audio is currently playing
```promql
alsa_pcm_state{type="playback"} == 3
```

### Count active audio streams
```promql
count(alsa_pcm_state == 3)
```

### Alert on audio device issues
```promql
alsa_pcm_state{card="1"} == 0 and alsa_pcm_state offset 5m == 3
```

## License

MIT License - See parent project for details.
