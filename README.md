# Wholesale Reorder Portal

A WooCommerce plugin for B2B wholesale customers to bulk-reorder products via CSV and save order templates.

## 🎯 The Business Problem

Wholesale buyers often have to email spreadsheets or manually search for each SKU on the website. This is slow and error-prone.

## 💡 The Solution

*   **CSV Upload:** Customers upload a CSV of SKUs and quantities.
*   **Line-by-Line Validation:** The system checks each line for valid SKUs, minimum quantities, and pack sizes.
*   **One-Click Add to Cart:** The entire validated order is added to the WooCommerce cart.
*   **Saved Templates:** Customers can save their most common orders for quick reordering.

## 🛠️ Technical Stack

*   **WordPress:** Custom Roles & Capabilities
*   **WooCommerce:** Cart & Product Integration
*   **PHP:** OOP Architecture
*   **JavaScript:** AJAX for CSV processing