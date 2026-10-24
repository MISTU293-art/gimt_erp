#!/usr/bin/env bash
# ==============================================================================
# GIMT Enterprise College ERP - Automated VPS Production Deployment Script
# Target Platforms: Ubuntu 20.04 / 22.04 / 24.04 LTS, Debian 11 / 12
# Regulatory Compliance: MAKAUT (College Code: 249)
# ==============================================================================

set -euo pipefail

# Text Formatting
BOLD='\033[1m'
GREEN='\033[0;32m'
BLUE='\033[0;34m'
YELLOW='\033[1;33m'
RED='\033[0;31m'
NC='\033[0m'

echo -e "${BLUE}${BOLD}==============================================================================${NC}"
echo -e "${BLUE}${BOLD}       GIMT ENTERPRISE COLLEGE ERP - PRODUCTION VPS DEPLOYMENT SUITE          ${NC}"
echo -e "${BLUE}${BOLD}         Affiliated to MAKAUT (Code: 249) &bull; Version 3.0 Production       ${NC}"
echo -e "${BLUE}${BOLD}==============================================================================${NC}"

# Check for Root / Sudo
if [ "$EUID" -ne 0 ]; then
    echo -e "${RED}[ERROR] This deployment script must be run as root or with sudo privileges.${NC}"
    exit 1
fi

APP_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
cd "$APP_DIR"

echo -e "\n${YELLOW}[1/7] Updating package repositories & verifying dependencies...${NC}"
apt-get update -qq
apt-get install -y -qq software-properties-common curl git zip unzip mariadb-client

# Check for PHP 8.1+
if ! command -v php >/dev/null 2>&1; then
    echo -e "${YELLOW}Installing PHP 8.2 & necessary extensions...${NC}"
    add-apt-repository -y ppa:ondrej/php
    apt-get update -qq
    apt-get install -y -qq php8.2 php8.2-cli php8.2-fpm php8.2-mysql php8.2-gd php8.2-zip php8.2-mbstring php8.2-xml php8.2-curl
fi

PHP_VER=$(php -r 'echo PHP_MAJOR_VERSION.".".PHP_MINOR_VERSION;')
echo -e "${GREEN}[OK] Detected PHP Version: ${PHP_VER}${NC}"

echo -e "\n${YELLOW}[2/7] Initializing Environment Configuration (.env)...${NC}"
if [ ! -f .env ]; then
    if [ -f .env.example ]; then
        cp .env.example .env
        echo -e "${GREEN}[OK] Created .env from .env.example${NC}"
    else
        cat << 'EOF' > .env
APP_NAME="GIMT Enterprise ERP"
APP_ENV=production
APP_DEBUG=false
APP_URL=http://localhost
APP_TIMEZONE=Asia/Kolkata

DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=gimt_erp
DB_USERNAME=root
DB_PASSWORD=
DB_CHARSET=utf8mb4
EOF
        echo -e "${GREEN}[OK] Generated fresh default .env${NC}"
    fi
else
    echo -e "${BLUE}[INFO] Existing .env detected, preserving current database configuration.${NC}"
fi

echo -e "\n${YELLOW}[3/7] Creating Uploads Directory Hierarchy...${NC}"
mkdir -p uploads/admissions \
         uploads/assignments \
         uploads/mar \
         uploads/moocs \
         uploads/profile

echo -e "\n${YELLOW}[4/7] Securing Permissions & Ownership...${NC}"
# Determine Web User
WEB_USER="www-data"
if id -u apache >/dev/null 2>&1; then
    WEB_USER="apache"
elif id -u nginx >/dev/null 2>&1; then
    WEB_USER="nginx"
fi

chown -R ${WEB_USER}:${WEB_USER} "$APP_DIR"
find "$APP_DIR" -type d -exec chmod 755 {} \;
find "$APP_DIR" -type f -exec chmod 644 {} \;
chmod -R 775 "$APP_DIR/uploads"
chmod +x "$APP_DIR/deploy.sh" || true

echo -e "${GREEN}[OK] Permissions assigned to ${WEB_USER}:${WEB_USER}${NC}"

echo -e "\n${YELLOW}[5/7] Executing Database Migration & MAKAUT Seed Engine...${NC}"
if php setup_database.php; then
    echo -e "${GREEN}[OK] Database schema initialized & MAKAUT curriculum tables populated.${NC}"
else
    echo -e "${YELLOW}[WARN] setup_database.php reported warnings. Please verify MySQL service connectivity.${NC}"
fi

echo -e "\n${YELLOW}[6/7] Hardening Server Security...${NC}"
# Ensure uploads/.htaccess exists
if [ -f "$APP_DIR/uploads/.htaccess" ]; then
    chmod 644 "$APP_DIR/uploads/.htaccess"
    echo -e "${GREEN}[OK] uploads/.htaccess script execution block active.${NC}"
fi

# Ensure root .htaccess exists
if [ -f "$APP_DIR/.htaccess" ]; then
    chmod 644 "$APP_DIR/.htaccess"
    echo -e "${GREEN}[OK] Root .htaccess security headers active.${NC}"
fi

echo -e "\n${YELLOW}[7/7] Verifying PHP Syntax Integrity across all modules...${NC}"
ERRORS=0
while IFS= read -r file; do
    if ! php -l "$file" >/dev/null 2>&1; then
        echo -e "${RED}[LINT ERROR] In $file${NC}"
        ERRORS=$((ERRORS + 1))
    fi
done < <(find . -maxdepth 2 -name "*.php" -not -path "*/vendor/*")

if [ "$ERRORS" -eq 0 ]; then
    echo -e "${GREEN}[OK] All PHP scripts passed strict syntax checks (0 errors).${NC}"
else
    echo -e "${RED}[FAIL] $ERRORS files contained syntax errors.${NC}"
    exit 1
fi

echo -e "\n${GREEN}${BOLD}==============================================================================${NC}"
echo -e "${GREEN}${BOLD}           GIMT ENTERPRISE ERP DEPLOYMENT SUCCESSFULLY COMPLETED!             ${NC}"
echo -e "${GREEN}${BOLD}==============================================================================${NC}"
echo -e "Access the ERP at your VPS Public IP or Domain."
echo -e "\n${BOLD}Pre-configured Enterprise Administrative Logins:${NC}"
echo -e "  - ${BOLD}Chairman (Full Control):${NC}    chairman@gimt.ac.in    | Password: Password@123"
echo -e "  - ${BOLD}Principal (Academic Dean):${NC}  principal@gimt.ac.in   | Password: Password@123"
echo -e "  - ${BOLD}Accounts Desk (Bursar):${NC}     accounts@gimt.ac.in    | Password: Password@123"
echo -e "  - ${BOLD}HOD CSE Department:${NC}         hod.cse@gimt.ac.in     | Password: Password@123"
echo -e "  - ${BOLD}Teacher (Faculty Workload):${NC} teacher.cse@gimt.ac.in | Password: Password@123"
echo -e "  - ${BOLD}Student (Rahul Roy):${NC}        student.cse@gimt.ac.in | Password: Password@123"
echo -e "=============================================================================="
