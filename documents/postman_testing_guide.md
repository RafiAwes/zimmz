# Postman Testing Guide: Order API

This guide provides instructions and payloads for testing the Order CRUD endpoints, following the project's standardized routing and naming conventions.

## 1. Authentication
Most endpoints require a JWT token.
- **Login**: `POST /api/auth/login`
- **Setup**: Copy the `access_token` from the login response.
- **Postman**: In the "Authorization" tab of your request, select **Type: Bearer Token** and paste your token.

---

## 2. API Endpoints

### 🟢 List Orders
- **Method**: `GET`
- **URL**: `http://10.10.10.45/api/order/get-all`
- **Headers**: `Accept: application/json`

### 🔵 Create Order (Food Delivery)
- **Method**: `POST`
- **URL**: `http://10.10.10.45/api/order/create`
- **Body**: `raw` (JSON)

**JSON Payload:**
```json
{
    "name": "John Doe",
    "details": "Extra spicy, no onions.",
    "time": "12:30 PM",
    "total_cost": 45.50,
    "drop_location": "Beach Hut 5",
    "type": "food_delivery",
    "restaurant_id": 1,
    "food_cost": 35.00,
    "delivery_fee": 5.50,
    "service_fee": 5.00,
    "special_instructions": "Buzz at the gate",
    "ready_now": true
}
```

**Bulk Edit Format:**
```text
name:John Doe
details:Extra spicy, no onions.
time:12:30 PM
total_cost:45.50
drop_location:Beach Hut 5
type:food_delivery
restaurant_id:1
food_cost:35.00
delivery_fee:5.50
service_fee:5.00
special_instructions:Buzz at the gate
ready_now:true
```

### 🔵 Create Order (Ferry Drop)
- **Method**: `POST`
- **URL**: `http://10.10.10.45/api/order/create`
- **Body**: `raw` (JSON)

**JSON Payload:**
```json
{
    "name": "Jane Smith",
    "details": "Fragile electronics.",
    "time": "3:00 PM",
    "total_cost": 65.00,
    "drop_location": "Main Pier",
    "type": "ferry_drops",
    "pickup_location": "Downtown Office",
    "ferry_id": 1,
    "island_id": 1,
    "drop_fee": 50.00,
    "package_fee": 15.00
}
```

**Bulk Edit Format:**
```text
name:Jane Smith
details:Fragile electronics.
time:3:00 PM
total_cost:65.00
drop_location:Main Pier
type:ferry_drops
pickup_location:Downtown Office
ferry_id:1
island_id:1
drop_fee:50.00
package_fee:15.00
```

### 🟠 Order Details
- **Method**: `GET`
- **URL**: `http://10.10.10.45/api/order/details/{id}`

### 🟠 Update Order
- **Method**: `PUT`
- **URL**: `http://10.10.10.45/api/order/update/{id}`
- **Body**: `raw` (JSON)

**JSON Payload:**
```json
{
    "status": "pending",
    "name": "Updated Name",
    "details": "Client changed their mind.",
    "total_cost": 50.00
}
```

**Bulk Edit Format:**
```text
status:pending
name:Updated Name
details:Client changed their mind.
total_cost:50.00
```

### 🔴 Delete Order
- **Method**: `DELETE`
- **URL**: `http://10.10.10.45/api/order/delete/{id}`
