#!/bin/bash
# install.sh - Install ALSA exporter as a systemd service

set -e

echo "=== ALSA Audio Exporter Installation ==="
echo ""

# Check if running as root
if [ "$EUID" -ne 0 ]; then 
    echo "ERROR: Please run as root (use sudo)"
    exit 1
fi

# Check if node_exporter textfile collector directory exists
TEXTFILE_DIR="/var/lib/node_exporter/textfile_collector"
if [ ! -d "$TEXTFILE_DIR" ]; then
    echo "Creating textfile collector directory: $TEXTFILE_DIR"
    mkdir -p "$TEXTFILE_DIR"
    chmod 755 "$TEXTFILE_DIR"
fi

# Copy the exporter script
echo "Installing audio_exporter.sh to /usr/local/bin/"
cp audio_exporter.sh /usr/local/bin/
chmod +x /usr/local/bin/audio_exporter.sh

# Copy the systemd service file
echo "Installing systemd service file"
cp alsa-exporter.service /etc/systemd/system/

# Reload systemd
echo "Reloading systemd daemon"
systemctl daemon-reload

# Enable the service
echo "Enabling alsa-exporter service"
systemctl enable alsa-exporter.service

# Start the service
echo "Starting alsa-exporter service"
systemctl start alsa-exporter.service

# Show status
echo ""
echo "=== Installation Complete ==="
echo ""
echo "Service status:"
systemctl status alsa-exporter.service --no-pager

echo ""
echo "Useful commands:"
echo "  - Check status:  systemctl status alsa-exporter"
echo "  - View logs:     journalctl -u alsa-exporter -f"
echo "  - Stop service:  systemctl stop alsa-exporter"
echo "  - Start service: systemctl start alsa-exporter"
echo "  - Restart:       systemctl restart alsa-exporter"
echo "  - Metrics file:  $TEXTFILE_DIR/audio_pcm.prom"
echo ""
