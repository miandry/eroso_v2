# Rapport Content Type - Installation Guide

## Overview
This module creates a "rapport" content type that automatically generates statistical reports for the inventory management system. Reports are automatically created/updated whenever products or stock movements are inserted or updated.

## Installation Steps

### 1. Reinstall the Module
Since we've added new configuration files, you need to reinstall the module:

```bash
cd /Applications/MAMP/htdocs/drupal
drush pm:uninstall mz_eroso_v2
drush pm:enable mz_eroso_v2
drush cr
```

### 2. Verify Content Type Creation
After installation, verify that the "rapport" content type was created:
- Go to: Structure > Content types
- You should see "Rapport" in the list

### 3. Verify Fields
The rapport content type should have the following fields:
- **field_total_products** (Integer) - Total number of products in catalog
- **field_total_stock** (Integer) - Total available stock units
- **field_total_stock_value** (Decimal) - Total value of stock based on sale prices
- **field_total_in** (Integer) - Total stock entries for the period
- **field_total_out** (Integer) - Total stock exits for the period
- **field_low_stock_count** (Integer) - Number of products with low stock (<=5 units)
- **field_period** (String) - Time period (Aujourd'hui, 7 jours, 30 jours, Tout)
- **field_category_stats** (Long text) - JSON data containing category breakdown

## How It Works

### Automatic Generation
Rapport nodes are automatically generated in the following scenarios:

1. **When a product is created** - `hook_node_insert()`
2. **When a product is updated** - `hook_node_update()`
3. **When a stock movement is created** - `hook_node_insert()` for stock bundle

### Period-Based Reports
The system generates 4 separate rapport nodes, one for each period:
- **Aujourd'hui** - Today's statistics
- **7 jours** - Last 7 days statistics
- **30 jours** - Last 30 days statistics
- **Tout** - All-time statistics

### Update Strategy
- If a rapport already exists for a period, it will be **updated** with new data
- If no rapport exists for a period, a new one will be **created**
- Only the most recent rapport per period is kept active

## Statistics Calculated

### Product Statistics
- Total number of products
- Total stock quantity across all products
- Total stock value (quantity × sale price)
- Number of products with low stock (≤5 units)
- Category breakdown (products per category)

### Movement Statistics (Period-Based)
- Total stock entries (IN movements) for the period
- Total stock exits (OUT movements) for the period

## API Access

The rapport data can be accessed via the existing API:

```
GET /api_solutions/api/v2/node/rapport
```

Filter by period:
```
GET /api_solutions/api/v2/node/rapport?filter[field_period]=Aujourd'hui
```

## Manual Generation

If you need to manually regenerate all rapports, you can call:

```php
_mz_eroso_v2_generate_rapport();
```

Or for a specific period:

```php
_mz_eroso_v2_create_rapport_for_period('Aujourd\'hui');
```

## Troubleshooting

### Rapports not being created
1. Check Drupal logs: `drush watchdog:show --type=mz_eroso_v2`
2. Verify the module is enabled: `drush pm:list | grep mz_eroso_v2`
3. Clear cache: `drush cr`

### Fields missing
1. Reinstall the module completely
2. Check field storage configuration files exist in `config/install/`
3. Verify permissions for the rapport content type

### Incorrect statistics
1. Check that products have `field_quantite_disponible` and `field_prix_vente` fields
2. Verify stock movements have `field_type` (in/out) and `field_quantite` fields
3. Check the period timestamp calculation in `_mz_eroso_v2_get_period_timestamp()`

## Notes

- Rapports are created with UID 1 (admin user)
- Category statistics are stored as JSON in `field_category_stats`
- The system uses the most recent rapport per period
- Stock movements are filtered by creation date for period-based statistics
