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

# Check if prometheus-node-exporter is installed
if ! command -v node_exporter &> /dev/null && ! systemctl is-active --quiet prometheus-node-exporter; then
    echo "Prometheus Node Exporter is not installed."
    echo "Installing prometheus-node-exporter..."
    
    if command -v apt-get &> /dev/null; then
        # Debian/Ubuntu
        apt-get update
        apt-get install -y prometheus-node-exporter
    elif command -v yum &> /dev/null; then
        # RHEL/CentOS
        yum install -y golang-github-prometheus-node-exporter
    elif command -v dnf &> /dev/null; then
        # Fedora
        dnf install -y golang-github-prometheus-node-exporter
    else
        echo "ERROR: Unable to install prometheus-node-exporter automatically."
        echo "Please install it manually before continuing."
        exit 1
    fi
    
    # Enable and start the service
    systemctl enable prometheus-node-exporter
    systemctl start prometheus-node-exporter
    echo "✓ Prometheus Node Exporter installed and started"
else
    echo "✓ Prometheus Node Exporter is already installed"
fi

# Configure node_exporter to bind to 0.0.0.0 using systemd override
echo "Configuring Prometheus Node Exporter to bind to 0.0.0.0:9100..."

# Create systemd override directory
mkdir -p /etc/systemd/system/prometheus-node-exporter.service.d

# Create override configuration
cat > /etc/systemd/system/prometheus-node-exporter.service.d/override.conf << 'EOF'
[Service]
ExecStart=
ExecStart=/usr/bin/prometheus-node-exporter --web.listen-address=0.0.0.0:9100 --collector.textfile.directory=/var/lib/node_exporter/textfile_collector
EOF

# Reload systemd and restart the service
systemctl daemon-reload
systemctl restart prometheus-node-exporter

echo "✓ Prometheus Node Exporter configured to listen on 0.0.0.0:9100"
echo ""
echo "Verifying configuration..."
sleep 2
if ss -tlnp | grep -q ':9100'; then
    echo "✓ Node Exporter is listening on port 9100"
    LISTEN_ADDR=$(ss -tlnp | grep ':9100' | awk '{print $4}' | head -n1)
    echo "  Listening on: $LISTEN_ADDR"
else
    echo "⚠ Warning: Could not verify Node Exporter is listening on port 9100"
    echo "  Check status with: systemctl status prometheus-node-exporter"
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
