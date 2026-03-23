#!/bin/bash

echo "==================================="
echo "Testing Spicy Noodle API"
echo "==================================="

# Test health check
echo -e "\n1. Testing Health Check..."
curl -s http://localhost:8000/api/health | jq '.'

# Test products list
echo -e "\n2. Testing Products List..."
curl -s http://localhost:8000/api/products | jq '.success, .pagination'

# Test categories list
echo -e "\n3. Testing Categories List..."
curl -s http://localhost:8000/api/categories | jq '.success, .pagination'

# Test tables list
echo -e "\n4. Testing Tables Available..."
curl -s http://localhost:8000/api/tables/available | jq '.success'

echo -e "\n==================================="
echo "Test completed!"
echo "==================================="
