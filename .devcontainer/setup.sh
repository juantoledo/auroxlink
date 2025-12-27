#!/bin/bash
set -e

echo "🌌 Setting up AuroxLink Development Environment..."

# Update package list
echo "📦 Updating package lists..."
sudo apt-get update

# Install Apache2 and PHP modules
echo "🌐 Installing Apache2 and PHP..."
sudo apt-get install -y \
    apache2 \
    libapache2-mod-php \
    php-cli \
    php-common \
    php-curl \
    php-json \
    php-mbstring \
    php-xml \
    php-zip

# Install network and system utilities
echo "🔧 Installing system utilities..."
sudo apt-get install -y \
    network-manager \
    alsa-utils \
    iproute2 \
    wireless-tools \
    net-tools \
    iputils-ping \
    sudo \
    curl \
    wget \
    vim \
    nano

# Install development tools
echo "🛠️ Installing development tools..."
sudo apt-get install -y \
    build-essential \
    git \
    unzip

# Configure Apache
echo "⚙️ Configuring Apache..."
sudo a2enmod rewrite
sudo a2enmod php8.2

# Create symbolic link to project directory
echo "🔗 Linking project to /var/www/html..."
sudo rm -rf /var/www/html
sudo ln -s /workspaces/auroxlink /var/www/html

# Set proper permissions
echo "🔐 Setting file permissions..."
# Set ownership to vscode user but keep www-data group for Apache access
sudo chown -R vscode:www-data /workspaces/auroxlink
sudo chmod -R 775 /workspaces/auroxlink
# Add both users to necessary groups
sudo usermod -aG audio www-data
sudo usermod -aG www-data vscode
sudo usermod -aG audio vscode

# Create mock SVXLink configuration directories (for development)
echo "📁 Creating mock SVXLink directories..."
sudo mkdir -p /etc/svxlink/svxlink.d
sudo mkdir -p /var/log
sudo touch /var/log/svxlink

# Create sample configuration files if they don't exist
if [ ! -f /etc/svxlink/svxlink.conf ]; then
    echo "📝 Creating sample svxlink.conf..."
    sudo tee /etc/svxlink/svxlink.conf > /dev/null <<'EOF'
[GLOBAL]
LOGICS=SimplexLogic
CFG_DIR=svxlink.d
TIMESTAMP_FORMAT="%c"

[SimplexLogic]
TYPE=Simplex
RX=Rx1
TX=Tx1
MODULES=ModuleEchoLink
CALLSIGN=TEST
SHORT_IDENT_INTERVAL=0
LONG_IDENT_INTERVAL=0

[Rx1]
TYPE=Local
AUDIO_DEV=alsa:default
AUDIO_CHANNEL=0
SQL_DET=VOX
SQL_START_DELAY=0
SQL_DELAY=0
SQL_HANGTIME=2000

[Tx1]
TYPE=Local
AUDIO_DEV=alsa:default
AUDIO_CHANNEL=0
PTT_TYPE=NONE
TIMEOUT=300
TX_DELAY=0
EOF
fi

if [ ! -f /etc/svxlink/svxlink.d/ModuleEchoLink.conf ]; then
    echo "📝 Creating sample ModuleEchoLink.conf..."
    sudo tee /etc/svxlink/svxlink.d/ModuleEchoLink.conf > /dev/null <<'EOF'
[ModuleEchoLink]
NAME=EchoLink
ID=6
TIMEOUT=300
CALLSIGN=TEST-L
PASSWORD=testpass
SYSOPNAME=Test Operator
LOCATION=Test Location
DEFAULT_LANG=en_US
MAX_QSOS=20
MAX_CONNECTIONS=5
LINK_IDLE_TIMEOUT=600
AUTOCON_ECHOLINK_ID=
EOF
fi

# Set permissions on config files
sudo chown www-data:www-data /etc/svxlink/svxlink.conf
sudo chown www-data:www-data /etc/svxlink/svxlink.d/ModuleEchoLink.conf
sudo chmod 664 /etc/svxlink/svxlink.conf
sudo chmod 664 /etc/svxlink/svxlink.d/ModuleEchoLink.conf

# Create includes directory and files if they don't exist
echo "📂 Ensuring includes directory structure..."
mkdir -p /workspaces/auroxlink/includes/logs

# Create environment.php if it doesn't exist
if [ ! -f /workspaces/auroxlink/includes/environment.php ]; then
    echo "📝 Creating sample environment.php..."
    cat > /workspaces/auroxlink/includes/environment.php <<'EOF'
<?php
// Configuración de entorno de desarrollo
$clave_acceso = "5f4dcc3b5aa765d61d8327deb882cf99"; // password (MD5)
$nombre_sistema = "AUROXLINK DEV";
$callsign_nodo = "TEST-L";
?>
EOF
fi

# Configure sudo permissions for www-data (development only)
echo "🔑 Configuring sudo permissions..."
sudo tee /etc/sudoers.d/99-www-data-svxlink > /dev/null <<'EOF'
www-data ALL=NOPASSWD: /bin/systemctl restart svxlink
www-data ALL=NOPASSWD: /bin/systemctl start svxlink
www-data ALL=NOPASSWD: /bin/systemctl stop svxlink
www-data ALL=NOPASSWD: /sbin/reboot
www-data ALL=(ALL) NOPASSWD: /usr/bin/nmcli, /usr/sbin/ip, /bin/systemctl
www-data ALL=(ALL) NOPASSWD: /sbin/iwlist
www-data ALL=(ALL) NOPASSWD: /usr/bin/amixer
www-data ALL=(ALL) NOPASSWD: /usr/bin/bash /tmp/update_auroxlink.sh
EOF
sudo chmod 440 /etc/sudoers.d/99-www-data-svxlink

# Create telegram config if it doesn't exist
if [ ! -f /workspaces/auroxlink/telegram_config.json ]; then
    echo "📱 Creating sample telegram_config.json..."
    cat > /workspaces/auroxlink/telegram_config.json <<'EOF'
{
    "token": "YOUR_TELEGRAM_BOT_TOKEN",
    "chat_id": "YOUR_CHAT_ID",
    "enabled": false
}
EOF
    chmod 664 /workspaces/auroxlink/telegram_config.json
fi

# Start Apache
echo "🚀 Starting Apache..."
sudo service apache2 start

# Display info
echo ""
echo "✅ AuroxLink Development Environment Ready!"
echo ""
echo "📡 Access the application at: http://localhost"
echo "🔐 Default password: password (MD5 hash already configured)"
echo ""
echo "📝 Development Notes:"
echo "   - SVXLink mock configs created in /etc/svxlink/"
echo "   - Apache is running and serving from /workspaces/auroxlink"
echo "   - www-data user has necessary sudo permissions"
echo "   - Project files are in /workspaces/auroxlink"
echo ""
echo "🎯 Quick Commands:"
echo "   - Restart Apache: sudo service apache2 restart"
echo "   - Check Apache status: sudo service apache2 status"
echo "   - View Apache logs: sudo tail -f /var/log/apache2/error.log"
echo "   - Edit configs: nano /etc/svxlink/svxlink.conf"
echo ""
echo "🌌 Happy coding! - CA2RDP"
echo ""
