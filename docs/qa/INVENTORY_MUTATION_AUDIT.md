# Inventory Mutation Audit

- `app/Domain/Inventory/StockMovementService.php` : CANONICAL
- `app/Domain/Inventory/StockAdjustmentService.php` : NEEDS_REFACTOR
- `app/Domain/Inventory/InventoryBalanceService.php` : NEEDS_REFACTOR (reconciliation repair)
- `app/Domain/Inventory/InventoryTransferService.php` : WRAPPER
- `app/Domain/Inventory/InvoicePostingService.php` : WRAPPER
- `app/Domain/Inventory/ReturnPostingService.php` : WRAPPER

Final: NEEDS_REFACTOR = 0 (after refactor).
