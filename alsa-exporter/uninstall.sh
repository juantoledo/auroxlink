#!/bin/bash
# uninstall.sh - Uninstall ALSA exporter service

set -e

echo "=== ALSA Audio Exporter Uninstallation ==="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "ERROR: Please run as root (use sudo)"
    exit 1
fi

# Stop the service if running
if systemctl is-active --quiet alsa-exporter.service; then
    echo "Stopping alsa-exporter service"
    systemctl stop alsa-exporter.service
fi

# Disable the service
if systemctl is-enabled --quiet alsa-exporter.service; then
    echo "Disabling alsa-exporter service"
    systemctl disable alsa-exporter.service
fi

# Remove service file
if [ -f "/etc/systemd/system/alsa-exporter.service" ]; then
    echo "Removing service file"
    rm /etc/systemd/system/alsa-exporter.service
fi

# Remove exporter script
if [ -f "/usr/local/bin/audio_exporter.sh" ]; then
    echo "Removing exporter script"
    rm /usr/local/bin/audio_exporter.sh
fi

# Remove metrics file
METRICS_FILE="/var/lib/node_exporter/textfile_collector/audio_pcm.prom"
if [ -f "$METRICS_FILE" ]; then
    echo "Removing metrics file"
    rm "$METRICS_FILE"
fi

# Reload systemd
echo "Reloading systemd daemon"
systemctl daemon-reload

echo ""
echo "=== Uninstallation Complete ==="
echo ""
