#!/bin/bash

# LendusFind ngrok setup script
# This script starts ngrok tunnels for backend, frontend, and websocket

set -e

echo "=========================================="
echo "  LendusFind - ngrok Remote Testing Setup"
echo "=========================================="

# Check if ngrok is authenticated
if ! ngrok config check &>/dev/null; then
    echo ""
    echo "ERROR: ngrok is not configured with an auth token."
    echo ""
    echo "To fix this:"
    echo "  1. Sign up at https://dashboard.ngrok.com/signup"
    echo "  2. Get your auth token from https://dashboard.ngrok.com/get-started/your-authtoken"
    echo "  3. Run: ngrok config add-authtoken YOUR_AUTH_TOKEN"
    echo ""
    exit 1
fi

echo ""
echo "Starting ngrok tunnels..."
echo ""
echo "This will create tunnels for:"
echo "  - Backend API (localhost:8000)"
echo "  - Frontend (localhost:5173)"
echo "  - WebSocket/Reverb (localhost:8081)"
echo ""
echo "IMPORTANT: After ngrok starts, you'll need to update your .env files"
echo "with the ngrok URLs. See the output below for the URLs."
echo ""
echo "Press Ctrl+C to stop all tunnels."
echo ""
echo "=========================================="

# Start ngrok with all tunnels
ngrok start --all
