#!/bin/bash

# Stinkin' Park Music Platform - Comprehensive Test Suite
# This script tests all major components and logs results

# Configuration
BASE_URL="http://localhost/radio"  # Adjust this to match your setup
LOG_FILE="test_results_$(date +%Y%m%d_%H%M%S).log"
VERBOSE=true

# Colors for output
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

# Initialize log file
echo "=====================================" > "$LOG_FILE"
echo "Stinkin' Park Test Suite" >> "$LOG_FILE"
echo "Started: $(date)" >> "$LOG_FILE"
echo "Base URL: $BASE_URL" >> "$LOG_FILE"
echo "=====================================" >> "$LOG_FILE"
echo "" >> "$LOG_FILE"

# Function to log messages
log() {
    local level=$1
    shift
    local message="$@"
    local timestamp=$(date "+%Y-%m-%d %H:%M:%S")
    
    echo "[$timestamp] [$level] $message" >> "$LOG_FILE"
    
    if [ "$VERBOSE" = true ]; then
        case $level in
            "ERROR")
                echo -e "${RED}[ERROR]${NC} $message"
                ;;
            "SUCCESS")
                echo -e "${GREEN}[SUCCESS]${NC} $message"
                ;;
            "WARNING")
                echo -e "${YELLOW}[WARNING]${NC} $message"
                ;;
            "INFO")
                echo -e "${BLUE}[INFO]${NC} $message"
                ;;
            *)
                echo "[$level] $message"
                ;;
        esac
    fi
}

# Function to test endpoint
test_endpoint() {
    local name=$1
    local url=$2
    local expected_code=${3:-200}
    
    log "INFO" "Testing: $name"
    log "INFO" "URL: $url"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$url")
    
    if [ "$response" = "$expected_code" ]; then
        log "SUCCESS" "$name returned expected code $expected_code"
        return 0
    else
        log "ERROR" "$name returned $response, expected $expected_code"
        return 1
    fi
}

# Function to test API endpoint with JSON response
test_api() {
    local name=$1
    local url=$2
    local check_field=$3
    
    log "INFO" "Testing API: $name"
    log "INFO" "URL: $url"
    
    response=$(curl -s "$url")
    
    # Check if response is valid JSON
    if echo "$response" | python3 -m json.tool > /dev/null 2>&1; then
        log "SUCCESS" "$name returned valid JSON"
        
        # Save response to log
        echo "API Response for $name:" >> "$LOG_FILE"
        echo "$response" | python3 -m json.tool >> "$LOG_FILE"
        echo "" >> "$LOG_FILE"
        
        # Check for specific field if provided
        if [ ! -z "$check_field" ]; then
            if echo "$response" | grep -q "\"$check_field\""; then
                log "SUCCESS" "$name contains field '$check_field'"
            else
                log "WARNING" "$name missing field '$check_field'"
            fi
        fi
        
        return 0
    else
        log "ERROR" "$name returned invalid JSON"
        echo "Raw response:" >> "$LOG_FILE"
        echo "$response" >> "$LOG_FILE"
        return 1
    fi
}

# Function to test database connection
test_database() {
    log "INFO" "Testing database connection"
    
    response=$(curl -s "$BASE_URL/test-db.php")
    
    if echo "$response" | grep -q "Database ready"; then
        log "SUCCESS" "Database connection successful"
        echo "Database test response:" >> "$LOG_FILE"
        echo "$response" >> "$LOG_FILE"
        return 0
    else
        log "ERROR" "Database connection failed"
        echo "Database test response:" >> "$LOG_FILE"
        echo "$response" >> "$LOG_FILE"
        return 1
    fi
}

# Function to test file upload (simulation)
test_upload_form() {
    log "INFO" "Testing upload form accessibility"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/admin/upload.php")
    
    if [ "$response" = "200" ]; then
        log "SUCCESS" "Upload form accessible"
        return 0
    else
        log "ERROR" "Upload form returned $response"
        return 1
    fi
}

# Function to get station list
test_station_list() {
    log "INFO" "Fetching station list"
    
    # Try to get a station slug from the database
    response=$(curl -s "$BASE_URL/api/station.php?slug=test-station")
    
    echo "Station API test response:" >> "$LOG_FILE"
    echo "$response" >> "$LOG_FILE"
    
    # Check various station slugs that might exist
    for slug in "hard-rock" "metal" "acoustic" "chill" "test-station"; do
        log "INFO" "Checking station: $slug"
        response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/api/station.php?slug=$slug")
        
        if [ "$response" = "200" ]; then
            log "SUCCESS" "Station '$slug' exists and is accessible"
            
            # Get full response for detailed check
            full_response=$(curl -s "$BASE_URL/api/station.php?slug=$slug")
            
            if echo "$full_response" | grep -q '"songs"'; then
                song_count=$(echo "$full_response" | grep -o '"total_songs":[0-9]*' | grep -o '[0-9]*')
                log "INFO" "Station '$slug' has $song_count songs"
            fi
            
            break
        fi
    done
}

# Function to test player page
test_player_page() {
    local slug=$1
    
    if [ -z "$slug" ]; then
        slug="test-station"
    fi
    
    log "INFO" "Testing player page for station: $slug"
    
    response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/stations/$slug")
    
    if [ "$response" = "200" ]; then
        log "SUCCESS" "Player page accessible for station '$slug'"
        return 0
    elif [ "$response" = "404" ]; then
        log "WARNING" "Station '$slug' not found (404)"
        return 1
    else
        log "ERROR" "Player page returned unexpected code: $response"
        return 1
    fi
}

# Function to run all tests
run_all_tests() {
    local total_tests=0
    local passed_tests=0
    local failed_tests=0
    
    echo ""
    echo "Starting Comprehensive Test Suite..."
    echo "===================================="
    
    # Test 1: Database Connection
    echo -n "Testing database connection... "
    if test_database; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Test 2: Home Page
    echo -n "Testing home page... "
    if test_endpoint "Home Page" "$BASE_URL/" 200; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Test 3: Admin Upload Page
    echo -n "Testing admin upload page... "
    if test_endpoint "Admin Upload" "$BASE_URL/admin/upload.php" 200; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Test 4: Admin Manage Page
    echo -n "Testing admin manage page... "
    if test_endpoint "Admin Manage" "$BASE_URL/admin/manage.php" 200; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Test 5: Admin Stations Page
    echo -n "Testing admin stations page... "
    if test_endpoint "Admin Stations" "$BASE_URL/admin/stations.php" 200; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Test 6: Station API
    echo -n "Testing station API... "
    test_station_list
    if [ $? -eq 0 ]; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Test 7: Player Page
    echo -n "Testing player page... "
    if test_player_page; then
        ((passed_tests++))
    else
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Test 8: Log API Endpoint
    echo -n "Testing log API endpoint... "
    test_data='{"level":"INFO","message":"Test log from bash script","page":"test","data":{"test":true}}'
    response=$(curl -s -X POST "$BASE_URL/api/log.php" \
        -H "Content-Type: application/json" \
        -d "$test_data" \
        -o /dev/null -w "%{http_code}")
    
    if [ "$response" = "200" ]; then
        log "SUCCESS" "Log API endpoint working"
        ((passed_tests++))
    else
        log "ERROR" "Log API returned $response"
        ((failed_tests++))
    fi
    ((total_tests++))
    
    # Summary
    echo ""
    echo "===================================="
    echo "Test Summary"
    echo "===================================="
    echo -e "${GREEN}Passed:${NC} $passed_tests"
    echo -e "${RED}Failed:${NC} $failed_tests"
    echo "Total: $total_tests"
    echo ""
    
    # Log summary
    log "INFO" "Test suite completed"
    log "INFO" "Total tests: $total_tests"
    log "INFO" "Passed: $passed_tests"
    log "INFO" "Failed: $failed_tests"
    
    if [ $failed_tests -eq 0 ]; then
        echo -e "${GREEN}✓ All tests passed!${NC}"
        log "SUCCESS" "All tests passed!"
        return 0
    else
        echo -e "${RED}✗ Some tests failed. Check $LOG_FILE for details.${NC}"
        log "ERROR" "Some tests failed"
        return 1
    fi
}

# Function to monitor logs in real-time
monitor_logs() {
    log "INFO" "Starting log monitor (press Ctrl+C to stop)"
    
    echo "Monitoring logs from API..."
    echo "============================"
    
    while true; do
        response=$(curl -s "$BASE_URL/api/logs.php?limit=10")
        
        if echo "$response" | python3 -m json.tool > /dev/null 2>&1; then
            clear
            echo "Latest logs (refreshed every 5 seconds):"
            echo "========================================="
            echo "$response" | python3 -m json.tool
        fi
        
        sleep 5
    done
}

# Function to test specific station
test_specific_station() {
    local slug=$1
    
    if [ -z "$slug" ]; then
        echo "Usage: $0 test-station <slug>"
        return 1
    fi
    
    log "INFO" "Testing specific station: $slug"
    
    # Test API
    echo "Testing Station API..."
    api_response=$(curl -s "$BASE_URL/api/station.php?slug=$slug")
    
    if echo "$api_response" | python3 -m json.tool > /dev/null 2>&1; then
        echo "API Response:"
        echo "$api_response" | python3 -m json.tool
        
        # Extract info
        if echo "$api_response" | grep -q '"success":true'; then
            song_count=$(echo "$api_response" | grep -o '"total_songs":[0-9]*' | grep -o '[0-9]*')
            station_name=$(echo "$api_response" | grep -o '"name":"[^"]*"' | cut -d'"' -f4)
            
            log "SUCCESS" "Station '$station_name' has $song_count songs"
            echo ""
            echo -e "${GREEN}✓ Station '$station_name' is working${NC}"
            echo "  Songs: $song_count"
        else
            log "ERROR" "Station API returned error"
            echo -e "${RED}✗ Station not found or error${NC}"
        fi
    else
        log "ERROR" "Invalid API response"
        echo "Raw response: $api_response"
    fi
    
    # Test Player Page
    echo ""
    echo "Testing Player Page..."
    player_response=$(curl -s -o /dev/null -w "%{http_code}" "$BASE_URL/stations/$slug")
    
    if [ "$player_response" = "200" ]; then
        log "SUCCESS" "Player page accessible"
        echo -e "${GREEN}✓ Player page is accessible${NC}"
        echo "  URL: $BASE_URL/stations/$slug"
    else
        log "ERROR" "Player page returned $player_response"
        echo -e "${RED}✗ Player page error (HTTP $player_response)${NC}"
    fi
}

# Main menu
show_menu() {
    echo ""
    echo "Stinkin' Park Test Suite"
    echo "========================"
    echo "1. Run all tests"
    echo "2. Test database connection"
    echo "3. Test specific station"
    echo "4. Monitor logs (real-time)"
    echo "5. View test results log"
    echo "6. Exit"
    echo ""
    echo -n "Select option: "
}

# Parse command line arguments
if [ "$1" = "all" ]; then
    run_all_tests
    exit $?
elif [ "$1" = "db" ]; then
    test_database
    exit $?
elif [ "$1" = "station" ] && [ ! -z "$2" ]; then
    test_specific_station "$2"
    exit $?
elif [ "$1" = "monitor" ]; then
    monitor_logs
    exit 0
elif [ "$1" = "help" ]; then
    echo "Usage: $0 [command] [options]"
    echo ""
    echo "Commands:"
    echo "  all              Run all tests"
    echo "  db               Test database connection"
    echo "  station <slug>   Test specific station"
    echo "  monitor          Monitor logs in real-time"
    echo "  help             Show this help message"
    echo ""
    echo "Interactive mode: Run without arguments"
    exit 0
fi

# Interactive mode
while true; do
    show_menu
    read choice
    
    case $choice in
        1)
            run_all_tests
            ;;
        2)
            test_database
            ;;
        3)
            echo -n "Enter station slug: "
            read slug
            test_specific_station "$slug"
            ;;
        4)
            monitor_logs
            ;;
        5)
            if [ -f "$LOG_FILE" ]; then
                less "$LOG_FILE"
            else
                echo "No log file found. Run tests first."
            fi
            ;;
        6)
            echo "Exiting..."
            exit 0
            ;;
        *)
            echo "Invalid option"
            ;;
    esac
done