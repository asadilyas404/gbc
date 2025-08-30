# Waiter App API Documentation

## Waiter Place Order API

**Endpoint:** `POST /api/v1/waiter/place-order`

**Description:** This API allows waiters to place orders for indoor dining customers through the mobile app.

---

## Request Headers

```
Content-Type: application/json
Accept: application/json
```

---

## Request Body Structure

```json
{
  "restaurant_id": 1,
  "table_id": 2,
  "order_type": "indoor",
  "waiter_id": 123,
  "cart": [
    {
      "id": 84,
      "quantity": 1,
      "price": 7.5,
      "variations": [
        {
          "heading": "Small Pizza 1",
          "selected_option": {
            "id": 1,
            "name": "Chicken Delight pizza (Small)"
          },
          "addons": [
            {
              "id": 8,
              "quantity": 1
            },
            {
              "id": 22,
              "quantity": 1
            }
          ]
        },
        {
          "heading": "Small Pizza 2", 
          "selected_option": {
            "id": 2,
            "name": "Chicken Shawarma (Small)"
          },
          "addons": [
            {
              "id": 16,
              "quantity": 1
            }
          ]
        },
        {
          "heading": "Small Pizza 3",
          "selected_option": {
            "id": 3,
            "name": "Fajita Pizza (Small)"
          },
          "addons": []
        }
      ],
      "add_ons": [
        {
          "id": 3,
          "quantity": 1
        }
      ],
      "add_on_qtys": [1],
      "notes": "Extra spicy please",
      "discount": 0
    }
  ],
//   "payment_method": "cash",
//   "cash_paid": 7.5,
//   "card_paid": 0,
  "customer_name": "Hassan",
  "phone": "+1234567890",
  "car_number": "",
//   "bank_account": ""
}
```

---

## Field Descriptions

### Required Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `restaurant_id` | integer | ID of the restaurant | 1 |
| `table_id` | integer | ID of the table where customer is seated | 2 |
| `order_type` | string | Type of order (indoor/outdoor/take_away/delivery) | "indoor" |
| `waiter_id` | integer | ID of the waiter placing the order (stored in order_taken_by) | 123 |
| `user_id` | integer | ID of the waiter user (same as waiter_id) | 123 |
| `cart` | array | Array of food items in the order | See cart structure below |

### Optional Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `customer_name` | string | Name of the customer | "Hassan" |
| `phone` | string | Phone number of the customer | "+1234567890" |
| `car_number` | string | Car number (for drive-thru) | "ABC123" |
| `bank_account` | string | Bank account details | "1234567890" |

### Payment Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `payment_method` | string | Payment method (cash/card/cash_card) | "cash" |
| `cash_paid` | numeric | Amount paid in cash | 7.5 |
| `card_paid` | numeric | Amount paid by card | 0 |

---

## Cart Item Structure

### Required Cart Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `id` | integer | Food item ID | 84 |
| `quantity` | integer | Quantity of the food item | 1 |
| `price` | numeric | Total price including variations and addons | 7.5 |

### Optional Cart Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `variations` | array | Selected variations for the food item | See variations structure below |
| `add_ons` | array | General addons for the food item | See addons structure below |
| `add_on_qtys` | array | Quantities for general addons | [1] |
| `notes` | string | Special instructions for the food item | "Extra spicy please" |
| `discount` | numeric | Discount amount on the food item | 0 |

---

## Variations Structure

### Required Variation Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `heading` | string | Name of the variation category | "Small Pizza 1" |
| `selected_option` | object | The selected option with id and name | See selected_option structure below |

### Optional Variation Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `addons` | array | Addons specific to this variation | See variation addons structure below |

---

## Selected Option Structure

### Required Selected Option Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `id` | integer | ID of the selected variation option | 1 |
| `name` | string | Name of the selected variation option | "Chicken Delight pizza (Small)" |

---

## Addons Structure

### Required Addon Fields

| Field | Type | Description | Example |
|-------|------|-------------|---------|
| `id` | integer | Addon ID | 8 |
| `quantity` | integer | Quantity of the addon | 1 |

---

## Response Format

### Success Response (200)

```json
{
  "status": true,
  "message": "Order placed successfully",
  "data": {
    "order_id": "123456789",
    "order_serial": "30-001",
    "order_amount": 7.5,
    "payment_status": "paid",
    "order_status": "pending",
    "table_id": 2,
    "order_type": "indoor",
    "created_at": "2025-08-30 13:39:10"
  }
}
```

### Error Response (422 - Validation Error)

```json
{
  "status": false,
  "message": "Validation failed",
  "errors": {
    "restaurant_id": ["The restaurant id field is required."],
    "table_id": ["The table id field is required."]
  }
}
```

### Error Response (400 - Business Logic Error)

```json
{
  "status": false,
  "message": "Product not found: 999"
}
```

### Error Response (500 - Server Error)

```json
{
  "status": false,
  "message": "Failed to place order: Database connection error"
}
```

---

## Important Notes for App Developer

### 1. Data Mapping from Your App Structure

Your app currently sends data in this format:
```json
{
  "order_type": "indoor",
  "customer_info": { "name": "hassan", "phone": "", "table_id": 2 },
  "order_items": [...],
  "order_summary": {...}
}
```

**You need to transform it to our API format:**

```json
{
  "restaurant_id": 1,           // Add this from your app config
  "table_id": 2,                // From customer_info.table_id
  "order_type": "indoor",       // From order_type
  "waiter_id": 123,             // Add this from logged-in waiter
  "user_id": 123,               // Same as waiter_id (waiter is the user)
  "customer_name": "hassan",    // From customer_info.name
  "phone": "",                  // From customer_info.phone
  "cart": [...]                 // Transform order_items to cart format
}
```

### 2. Cart Transformation

**From your format:**
```json
{
  "foodId": 84,
  "food": { "id": 84, "name": "3 Small Pizza", ... },
  "quantity": 1,
  "selectedVariations": [...],
  "selectedVariationAddons": [...],
  "selectedAddons": [],
  "totalPrice": 7.5
}
```

**To our format:**
```json
{
  "id": 84,                     // From foodId
  "quantity": 1,                // From quantity
  "price": 7.5,                 // From totalPrice
  "variations": [                // Transform selectedVariations
    {
      "heading": "Small Pizza 1",
      "selected_option": {
        "id": 1,                 // You need to get this ID from your variation data
        "name": "Chicken Delight pizza (Small)"
      },
      "addons": [
        { "id": 8, "quantity": 1 }  // From selectedVariationAddons
      ]
    }
  ],
  "add_ons": [3],               // From selectedAddons
  "add_on_qtys": [1]            // Quantities for selectedAddons
}
```

### 3. Required App Changes

1. **Add restaurant_id** to your app configuration
2. **Add waiter_id** from the logged-in waiter session
3. **Set user_id** same as waiter_id (waiter is the user)
4. **Transform order_items** to cart format
5. **Map selectedVariations** to variations with selected_option (including ID)
6. **Map selectedVariationAddons** to variation addons
7. **Map selectedAddons** to general add_ons

### 4. Example Complete Request

```json
{
  "restaurant_id": 1,
  "table_id": 2,
  "order_type": "indoor",
  "waiter_id": 123,
  "user_id": 123,
  "customer_name": "hassan",
  "phone": "",
  "cart": [
    {
      "id": 84,
      "quantity": 1,
      "price": 7.5,
      "variations": [
        {
          "heading": "Small Pizza 1",
          "selected_option": {
            "id": 1,
            "name": "Chicken Delight pizza (Small)"
          },
          "addons": [
            { "id": 8, "quantity": 1 },
            { "id": 22, "quantity": 1 }
          ]
        },
        {
          "heading": "Small Pizza 2",
          "selected_option": {
            "id": 2,
            "name": "Chicken Shawarma (Small)"
          },
          "addons": [
            { "id": 16, "quantity": 1 }
          ]
        },
        {
          "heading": "Small Pizza 3",
          "selected_option": {
            "id": 3,
            "name": "Fajita Pizza (Small)"
          },
          "addons": []
        }
      ],
      "add_ons": [3],
      "add_on_qtys": [1],
      "notes": "",
      "discount": 0
    }
  ],
  "payment_method": "cash",
  "cash_paid": 7.5,
  "card_paid": 0
}
```

---

## Testing the API

1. **Use Postman or similar tool**
2. **Set method to POST**
3. **Set URL to:** `https://yourdomain.com/api/v1/waiter/place-order`
4. **Set Content-Type header to:** `application/json`
5. **Send the request body in the format shown above**

---

## Support

If you have any questions about implementing this API, please contact the development team with:
- Your app's current data structure
- Any specific requirements or constraints
- Error messages you encounter during testing
