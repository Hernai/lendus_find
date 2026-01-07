#!/bin/bash

# LendusFind Development Server Script
# Usage: ./dev.sh [command]
# Commands: start, stop, backend, frontend, install

PROJECT_DIR="$(cd "$(dirname "$0")" && pwd)"
BACKEND_DIR="$PROJECT_DIR/backend"
FRONTEND_DIR="$PROJECT_DIR/frontend"

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

print_header() {
    echo -e "${BLUE}"
    echo "╔═══════════════════════════════════════════╗"
    echo "║         LendusFind Dev Server             ║"
    echo "╚═══════════════════════════════════════════╝"
    echo -e "${NC}"
}

start_backend() {
    echo -e "${GREEN}▶ Starting Laravel Backend...${NC}"
    cd "$BACKEND_DIR"
    php artisan serve --host=localhost --port=8000 &
    echo $! > "$PROJECT_DIR/.backend.pid"
    echo -e "${GREEN}✓ Backend running at http://localhost:8000${NC}"
}

start_frontend() {
    echo -e "${GREEN}▶ Starting Vue.js Frontend...${NC}"
    cd "$FRONTEND_DIR"
    npm run dev -- --host --port 5173 &
    echo $! > "$PROJECT_DIR/.frontend.pid"
    echo -e "${GREEN}✓ Frontend running at http://localhost:5173${NC}"
}

stop_servers() {
    echo -e "${YELLOW}⏹ Stopping servers...${NC}"

    if [ -f "$PROJECT_DIR/.backend.pid" ]; then
        kill $(cat "$PROJECT_DIR/.backend.pid") 2>/dev/null
        rm "$PROJECT_DIR/.backend.pid"
        echo -e "${GREEN}✓ Backend stopped${NC}"
    fi

    if [ -f "$PROJECT_DIR/.frontend.pid" ]; then
        kill $(cat "$PROJECT_DIR/.frontend.pid") 2>/dev/null
        rm "$PROJECT_DIR/.frontend.pid"
        echo -e "${GREEN}✓ Frontend stopped${NC}"
    fi

    # Kill any remaining processes on the ports
    lsof -ti:8000 | xargs kill -9 2>/dev/null
    lsof -ti:5173 | xargs kill -9 2>/dev/null
}

install_deps() {
    echo -e "${BLUE}📦 Installing dependencies...${NC}"

    echo -e "${YELLOW}→ Backend (Composer)${NC}"
    cd "$BACKEND_DIR" && composer install

    echo -e "${YELLOW}→ Frontend (npm)${NC}"
    cd "$FRONTEND_DIR" && npm install

    echo -e "${GREEN}✓ Dependencies installed${NC}"
}

refresh_backend() {
    echo -e "${YELLOW}🔄 Refreshing Backend...${NC}"
    cd "$BACKEND_DIR"
    php artisan cache:clear
    php artisan config:clear
    php artisan route:clear
    php artisan view:clear
    echo -e "${GREEN}✓ Backend cache cleared${NC}"
}

case "$1" in
    start)
        print_header
        stop_servers
        start_backend
        start_frontend
        echo ""
        echo -e "${GREEN}═══════════════════════════════════════════${NC}"
        echo -e "${GREEN}  Backend:  http://localhost:8000${NC}"
        echo -e "${GREEN}  Frontend: http://localhost:5173${NC}"
        echo -e "${GREEN}═══════════════════════════════════════════${NC}"
        echo ""
        echo -e "${YELLOW}Press Ctrl+C to stop all servers${NC}"
        wait
        ;;
    stop)
        stop_servers
        ;;
    backend)
        print_header
        cd "$BACKEND_DIR"
        php artisan serve --host=localhost --port=8000
        ;;
    frontend)
        print_header
        cd "$FRONTEND_DIR"
        npm run dev -- --host --port 5173
        ;;
    install)
        print_header
        install_deps
        ;;
    refresh)
        refresh_backend
        ;;
    *)
        print_header
        echo "Usage: ./dev.sh [command]"
        echo ""
        echo "Commands:"
        echo "  start     Start both backend and frontend"
        echo "  stop      Stop all running servers"
        echo "  backend   Start only Laravel backend"
        echo "  frontend  Start only Vue.js frontend"
        echo "  install   Install all dependencies"
        echo "  refresh   Clear Laravel caches"
        echo ""
        ;;
esac
