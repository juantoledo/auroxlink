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
- Prometheus node_exporter with textfile collector enabled (will be installed automatically if not present)
- Root access for installation

## Installation

```bash
cd alsa-exporter
sudo ./install.sh
```

The installer will:

1. Check for and install prometheus-node-exporter if not present
2. Configure Node Exporter to bind to 0.0.0.0:9100 (accessible from any network interface)
3. Create the textfile collector directory if needed
4. Copy the exporter script to `/usr/local/bin/`
5. Install and enable the systemd service
6. Start the service automatically

## Configuration

Edit `/usr/local/bin/audio_exporter.sh` to change:

- `CARDS="0 1 2 3 4"` - Space-separated list of sound card numbers to monitor (automatically skips non-existent cards)
- `METRICS_FILE` - Change metrics file location
- `sleep 1` - Adjust scraping interval

After changes, restart the service:

```bash
sudo systemctl restart alsa-exporter
```

## Usage

### Access metrics in browser

The installer configures Prometheus Node Exporter to bind to 0.0.0.0:9100, making it accessible from any network interface.

**Local access:**

```
http://localhost:9100/metrics
```

**Remote access (from any machine on the network):**

```
http://your-server-ip:9100/metrics
```

For example:

```
http://192.168.1.100:9100/metrics
```

To see only the ALSA audio metrics, search for `alsa_pcm` in the output.

**Note:** Make sure port 9100 is open in your firewall if accessing from remote machines.

### View metrics file directly

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
