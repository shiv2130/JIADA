# Change Log (latest changes)

## Pages
- `pages/lease-transfer.php`: Added full client-side validation, MSME premium preview, and document limits for lease transfers.
- `pages/hrms.php`: Added inline forms for employees, attendance, leave, payroll and refreshed data tables.
- `pages/brap.php`: New BRAP tracker page with add/filter/view for compliance initiatives.
- `pages/survey.php`: New GIS map page (Leaflet) to view land parcels from survey API.

## APIs
- `api/lease/transfer.php`: RBAC actions (approve/reject/objection), attachment handling, premium calc based on lease premium, notifications.
- `api/hrms/employees.php`: GET filters + POST create/update employee.
- `api/hrms/attendance.php`: GET filters + POST attendance upsert with auto status.
- `api/hrms/leave.php`: GET filters + POST leave request.
- `api/hrms/payroll.php`: GET filters + POST payroll processing (basic HRA/DA/PF/PT math).
- `api/brap/items.php`: List/create/update BRAP items (status/owner/category/evidence).
- `api/survey/parcels.php`: GeoJSON output for land parcels (WKT → GeoJSON).
- `api/survey/verify.php`: Accept parcel verification with geojson, notes, photos; optional land_banks status update.

## Routing
- `index.php`: Added routes for BRAP, Survey APIs/pages; ensured `uploads/survey` directory creation.

## Database (create_missing_tables.sql)
- Added tables: `brap_items`, `survey_submissions`.

