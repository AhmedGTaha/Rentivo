# Rentivo
## Software Requirements Specification (SRS)

**Document Version:** 1.0  
**Product Name:** Rentivo  
**Product Type:** Multi-agency car rental SaaS  
**Implementation Style:** Plain PHP, component-driven, no full-stack framework  
**Primary Market:** Bahrain, with architecture suitable for later GCC expansion  
**Default Currency:** BHD  
**Default Timezone:** Asia/Bahrain  
**Authentication:** Google OAuth only  
**Status:** Approved implementation specification  

---

# 1. Document Purpose

This document is the source of truth for implementing **Rentivo**, a multi-agency car rental SaaS platform.

Rentivo allows independent car rental agencies to create and manage their own organization inside one shared platform. Each organization has its own:

- name
- slug
- logo
- branding
- contact details
- locations
- vehicle fleet
- customers
- bookings
- employees
- employee permissions
- operational records
- customer document reviews
- notifications
- reports
- audit history

Public visitors may browse cars, search, filter, sort, view agency storefronts, and view car details without creating an account.

Authentication is required only when a visitor attempts an account-specific action such as:

- creating a booking
- saving a favorite
- accessing booking history
- managing profile information
- uploading customer documents
- viewing notifications
- creating or managing an organization

This document must be treated as the implementation contract.

Where implementation details are not explicitly defined, prefer:

1. security
2. tenant isolation
3. data integrity
4. reusable components
5. simple maintainable implementation
6. consistency with this SRS

Do not invent major product behavior that contradicts this specification.

---

# 2. Product Summary

Rentivo is a shared marketplace and management platform for car rental agencies.

Public flow:

```text
Visitor
  ↓
Browse Agencies / Cars
  ↓
Search + Filter + Sort
  ↓
View Car Details
  ↓
Select Dates
  ↓
Book
  ↓
Google Authentication if needed
  ↓
Booking Checkout
  ↓
Pending Booking
```

Customer account:

```text
Account
├── Dashboard
├── Bookings
├── Favorites
├── Profile
├── Documents
├── Notifications
└── Settings
```

Organization management:

```text
Organization
├── Dashboard
├── Cars
├── Categories
├── Locations
├── Bookings
├── Customers
├── Documents
├── Employees
├── Reports
├── Activity
└── Settings
```

Organization access model:

```text
Organization Admin
└── Full organization access

Employees
└── Explicit permissions assigned by organization admin
```

---

# 3. Product Goals

Rentivo must:

- allow multiple rental agencies to operate independently in one platform
- isolate all organization-owned business data
- provide a polished public vehicle marketplace
- require no authentication for normal browsing
- make booking simple and focused
- support Google-only authentication
- support organization admins and permission-controlled employees
- support complete booking, pickup, rental, and return workflows
- keep customer identity separate from organization roles
- store car images locally for version 1
- store sensitive customer documents privately outside the public web directory
- use reusable backend and frontend components
- avoid heavy frameworks and unnecessary infrastructure
- be maintainable enough for future payments, subscriptions, Arabic, and integrations

---

# 4. Explicit Non-Goals for Version 1

Do not implement the following unless separately approved:

- Laravel
- Symfony
- React
- Vue
- Angular
- Livewire
- Node.js application backend
- Firebase
- S3
- Google Cloud Storage
- organization subdomains
- custom organization domains
- Stripe
- BenefitPay
- online payment gateway
- Apple login
- password authentication
- email/password registration
- phone OTP
- SMS
- WhatsApp integration
- coupons
- promo codes
- dynamic seasonal pricing
- loyalty points
- customer reviews
- mobile application
- advanced maintenance work orders
- organization subscription billing
- AI features
- third-party public API
- GraphQL
- microservices
- Redis
- message queues
- Kubernetes
- Docker as a mandatory runtime dependency

The implementation may be structured so these can be added later, but version 1 must not be over-engineered for them.

---

# 5. Technology Stack

## 5.1 Backend

Use:

- PHP 8.3+ or latest stable PHP 8.x supported by the deployment environment
- strict typing where practical
- Composer autoloading
- PDO
- MySQL 8+

No full application framework.

---

## 5.2 Frontend

Use:

- semantic HTML5
- CSS
- vanilla JavaScript
- server-rendered PHP views

A lightweight icon library such as **Lucide Icons** may be used.

No SPA framework.

---

## 5.3 Authentication

Use Google OAuth 2.0 only.

Recommended Composer package:

```text
google/apiclient
```

---

## 5.4 Email

Use SMTP through:

```text
phpmailer/phpmailer
```

Email notifications are supported, but the application must remain functional if SMTP is disabled in a development environment.

---

## 5.5 Environment Configuration

Use:

```text
vlucas/phpdotenv
```

or equivalent simple environment loader.

Do not commit secrets.

---

## 5.6 Testing

Use PHPUnit.

Priority test areas:

- authorization
- tenant isolation
- booking conflicts
- booking status transitions
- permission enforcement
- money calculations
- private document access
- Google invitation matching
- CSRF handling

---

# 6. Architectural Principles

## 6.1 Component-Driven System

Rentivo must be implemented as reusable building blocks.

The project must not be structured as a collection of isolated PHP pages containing their own duplicated SQL, authorization, business rules, HTML, and CSS.

Preferred flow:

```text
Request
  ↓
Router / Controller
  ↓
Authorization
  ↓
Service
  ↓
Repository
  ↓
Database
  ↓
View
  ↓
Reusable UI Components
```

---

## 6.2 UI Components Must Not Query the Database

Incorrect:

```text
CarCard component
  ↓
Executes SQL
```

Correct:

```text
Repository
  ↓
Controller
  ↓
CarCard($car)
```

UI components render supplied data only.

---

## 6.3 Business Logic Must Be Reusable

Example:

```text
BookingService
├── AvailabilityService
├── PricingService
├── NotificationService
├── AuditService
└── BookingRepository
```

The same services should be reusable by:

- customer booking
- organization booking management
- reports
- scheduled tasks
- dashboard summaries

---

## 6.4 Avoid Meaningful Duplication

If a meaningful behavior or visual pattern appears more than once, extract it.

Centralize at minimum:

- authentication checks
- organization resolution
- permission checks
- CSRF
- flash messages
- validation helpers
- upload validation
- image processing
- private file delivery
- currency formatting
- date formatting
- pagination
- status badges
- booking transitions
- audit logging
- notifications
- reusable table components
- confirmation dialogs

---

# 7. Suggested Project Structure

```text
rentivo/
│
├── app/
│   ├── Auth/
│   │   ├── GoogleAuthService.php
│   │   └── SessionAuth.php
│   │
│   ├── Components/
│   │   └── ViewComponent.php
│   │
│   ├── Database/
│   │   ├── Connection.php
│   │   └── Transaction.php
│   │
│   ├── Http/
│   │   ├── Request.php
│   │   ├── Response.php
│   │   ├── Router.php
│   │   └── Controllers/
│   │
│   ├── Repositories/
│   │   ├── UserRepository.php
│   │   ├── OrganizationRepository.php
│   │   ├── CarRepository.php
│   │   ├── BookingRepository.php
│   │   └── ...
│   │
│   ├── Security/
│   │   ├── Authorization.php
│   │   ├── Csrf.php
│   │   └── RateLimiter.php
│   │
│   ├── Services/
│   │   ├── OrganizationService.php
│   │   ├── EmployeeService.php
│   │   ├── CarService.php
│   │   ├── AvailabilityService.php
│   │   ├── PricingService.php
│   │   ├── BookingService.php
│   │   ├── RentalService.php
│   │   ├── DocumentService.php
│   │   ├── NotificationService.php
│   │   ├── AuditService.php
│   │   ├── FileStorageService.php
│   │   └── ImageService.php
│   │
│   ├── Support/
│   │   ├── Currency.php
│   │   ├── DateTimeHelper.php
│   │   ├── Pagination.php
│   │   ├── Slug.php
│   │   └── Flash.php
│   │
│   └── Validation/
│       └── Validator.php
│
├── config/
│   └── app.php
│
├── database/
│   ├── migrations/
│   └── seeds/
│
├── public/
│   ├── index.php
│   ├── .htaccess
│   │
│   ├── assets/
│   │   ├── css/
│   │   │   ├── tokens.css
│   │   │   ├── base.css
│   │   │   ├── layout.css
│   │   │   ├── components/
│   │   │   └── pages/
│   │   │
│   │   ├── js/
│   │   │   ├── app.js
│   │   │   └── components/
│   │   │
│   │   └── images/
│   │
│   └── uploads/
│       ├── organizations/
│       ├── cars/
│       └── profiles/
│
├── routes/
│   └── web.php
│
├── scripts/
│   ├── migrate.php
│   ├── seed.php
│   └── scheduler.php
│
├── storage/
│   ├── private/
│   │   ├── documents/
│   │   └── inspections/
│   └── logs/
│
├── tests/
│
├── views/
│   ├── components/
│   │   ├── layout/
│   │   ├── navigation/
│   │   ├── forms/
│   │   ├── feedback/
│   │   ├── cars/
│   │   ├── bookings/
│   │   ├── organizations/
│   │   └── dashboard/
│   │
│   ├── public/
│   ├── account/
│   ├── manage/
│   └── dev/
│
├── vendor/
│
├── .env
├── .env.example
├── .gitignore
├── composer.json
├── README.md
└── SRS.md
```

This structure is a guideline. Claude Code may adjust small details while preserving the architectural principles.

---

# 8. Actors

## 8.1 Public Visitor

May:

- view homepage
- browse agencies
- browse cars
- search
- filter
- sort
- view car details
- view public organization storefronts
- check date-based availability
- start booking flow
- authenticate with Google

May not:

- create a booking before authentication
- favorite a car before authentication
- access account pages
- access organization management
- upload private documents

---

## 8.2 Customer

A Google-authenticated platform user.

May:

- perform all public visitor actions
- create bookings
- cancel eligible own bookings
- view own bookings
- favorite cars
- remove favorites
- manage own profile
- upload own documents
- see document states
- read own notifications
- manage basic preferences

A customer may book from multiple organizations.

---

## 8.3 Organization Admin

Organization admin has full authority within one organization.

May:

- edit organization settings
- manage organization logo
- manage locations
- manage categories
- manage cars
- manage car images
- manage bookings
- confirm bookings
- reject bookings
- cancel bookings
- manage payment status
- process pickups
- process returns
- view organization customers
- review customer documents
- invite employees
- assign employee permissions
- remove employees
- view reports
- view activity logs

Admin permissions are implicit.

Do not store a giant list of permission rows for admins.

---

## 8.4 Organization Employee

An employee belongs to an organization and receives explicit permissions.

The backend must enforce permissions.

Hiding UI elements is not sufficient authorization.

---

# 9. User and Organization Relationship

A single Google user may simultaneously be:

- a customer
- an admin of Organization A
- an employee of Organization B

Do not create separate login systems.

Roles are contextual to organization membership.

Example:

```text
User Ahmed
├── Customer on platform
├── Admin of Speedy Rentals
└── Employee of Luxury Drive
```

---

# 10. Google Authentication

## 10.1 Only Authentication Method

There shall be:

- no password column used for authentication
- no password login
- no registration form with password
- no forgot-password flow
- no password reset
- no local credentials

---

## 10.2 Login Page

Primary action:

```text
Continue with Google
```

The same flow handles new accounts and returning users.

---

## 10.3 Google User Requirements

On successful OAuth:

- validate OAuth state
- validate returned Google identity
- require verified Google email
- obtain Google subject ID
- obtain email
- obtain display name
- obtain avatar if available

Create local user on first login.

---

## 10.4 User Record

Store at minimum:

- id
- google_id
- email
- name
- google_avatar_url
- email_verified_at
- created_at
- updated_at

Do not store Google access/refresh tokens unless a future Google API integration explicitly requires them.

---

## 10.5 Intended Destination

If authentication was triggered from:

```text
/cars/{slug}/book
```

preserve:

- selected car
- selected organization
- pickup date/time
- return date/time
- pickup location
- return location

After authentication, return directly to booking checkout.

---

## 10.6 Session Requirements

After authentication:

- regenerate session ID
- store local user ID
- use HttpOnly cookies
- use Secure cookies in production
- use SameSite=Lax

Logout:

- destroys local session
- does not delete Google account
- returns to homepage

---

# 11. User Profile

Profile fields:

- full name
- email, read-only
- phone
- date of birth, optional
- nationality, optional
- address, optional
- profile image path, optional
- created_at
- updated_at

Google avatar may be displayed if the user has not uploaded a local profile image.

A phone number is required before a booking can be submitted.

---

# 12. Organization Model

Each rental agency is an organization.

Organization fields:

- id
- name
- slug
- logo_path
- description
- contact_email
- phone
- address
- primary_color
- rental_terms
- is_active
- created_at
- updated_at

---

## 12.1 Public Organization URLs

```text
/agencies
/agency/{slug}
/agency/{slug}/cars
/agency/{slug}/cars/{carSlug}
```

---

## 12.2 Management URLs

```text
/manage/{organizationSlug}
/manage/{organizationSlug}/cars
/manage/{organizationSlug}/bookings
...
```

---

## 12.3 Organization Creation

Any authenticated user may create an organization.

Required:

- name
- unique slug
- contact email
- phone

Optional initially:

- logo
- description
- address
- primary color
- rental terms

The creator becomes the organization admin.

---

# 13. Organization Membership

Table:

```text
organization_users
```

Fields:

- id
- organization_id
- user_id
- role
- created_at

Allowed roles:

```text
admin
employee
```

Unique:

```text
(organization_id, user_id)
```

---

# 14. Employee Permission System

Required permissions:

```text
cars.view
cars.create
cars.edit
cars.archive
cars.manage_images

bookings.view
bookings.confirm
bookings.reject
bookings.cancel
bookings.checkout
bookings.complete_return
bookings.manage_payment

customers.view
customers.edit_notes

documents.view
documents.verify

locations.view
locations.manage

reports.view
```

Organization admins automatically have all organization authority.

Admin-only actions in version 1:

- invite employees
- remove employees
- edit employee permissions
- change organization settings
- archive/deactivate organization
- manage organization admin authority

Employees cannot:

- change their own permissions
- change another employee's permissions
- promote themselves
- remove the admin
- delete the organization

---

# 15. Employee Invitations

Admin invitation flow:

```text
Admin enters Google email
  ↓
Admin selects permissions
  ↓
Invitation stored
  ↓
Invite link sent
  ↓
Employee opens link
  ↓
Continue with Google
  ↓
Verified Google email must match invite
  ↓
Membership created
  ↓
Selected permissions assigned
```

Invitation fields:

- id
- organization_id
- email
- token_hash
- invited_by_user_id
- expires_at
- accepted_at
- created_at

Default expiration:

```text
7 days
```

Never store raw invitation token.

Store only a cryptographic hash.

Invitation permissions may be stored in a related invitation-permission table or equivalent normalized structure.

---

# 16. Authorization Rules

Every protected organization request must verify:

1. authenticated user
2. valid organization
3. user membership in organization
4. role
5. required employee permission where relevant
6. requested entity belongs to the organization

Example:

Request:

```text
/manage/speedy-rentals/cars/72/edit
```

Car lookup must include:

```text
cars.id = 72
AND
cars.organization_id = current_organization.id
```

Never:

1. load car globally by ID
2. trust organization slug later

Tenant isolation is mandatory.

---

# 17. Public Homepage

The homepage should include:

- Rentivo branding
- primary navigation
- strong automotive hero
- Browse Cars CTA
- featured agencies
- featured cars
- category/brand discovery
- simple "How it works"
- platform feature section
- large final CTA
- minimal footer

Suggested hero copy direction:

```text
Find Your
Perfect Car
in Bahrain

Browse cars from trusted rental agencies
in one premium marketplace.

[ Browse Cars ]
```

Do not hard-code final marketing copy deeply into business logic.

---

# 18. Public Agency Listing

Route:

```text
/agencies
```

Show active organizations.

Agency card includes:

- logo
- name
- short description
- primary address/location
- active fleet count where useful

Selecting agency opens storefront.

---

# 19. Agency Storefront

Each organization storefront displays:

- organization logo
- organization name
- description
- contact information
- address
- brand accent
- rental terms
- available/public cars
- locations where useful

Organization primary color may affect accents only.

Organizations must not be allowed to inject custom HTML, JavaScript, or arbitrary CSS.

---

# 20. Car Categories

Categories belong to organizations.

Fields:

- id
- organization_id
- name
- slug
- created_at
- updated_at

Examples:

- Economy
- Sedan
- SUV
- Luxury
- Sports
- Van

Do not hard-code categories.

Unique:

```text
(organization_id, name)
```

---

# 21. Locations

Each organization may have multiple locations.

Fields:

- id
- organization_id
- name
- address
- phone
- opening_hours
- latitude, nullable
- longitude, nullable
- is_active
- created_at
- updated_at

Cars may belong to a location.

Bookings contain pickup and return location references.

---

# 22. Cars

Car fields:

- id
- organization_id
- category_id
- location_id
- brand
- model
- slug
- year
- plate_number
- vin
- transmission
- fuel_type
- seats
- doors
- mileage
- color
- daily_rate_fils
- description
- status
- archived_at
- created_at
- updated_at

Unique:

```text
(organization_id, slug)
```

---

# 23. Car Status

Allowed:

```text
available
reserved
rented
maintenance
inactive
```

Meanings:

### available
Operational and generally rentable.

### reserved
Operational but currently reserved/held for a near-term confirmed booking.

### rented
Currently checked out.

### maintenance
Unavailable for operational reasons.

### inactive
Not available for new rentals.

Car status alone must not determine future availability.

Date-based bookings must also be evaluated.

---

# 24. Car Images

Each car may have up to 10 marketing images.

Fields:

- id
- car_id
- file_path
- display_order
- is_primary
- created_at

Rules:

- zero or one primary image
- primary image shown on card/listing
- allow ordering
- deleting primary image allows another image to become primary
- image upload must be server validated
- use random server-generated filenames
- prefer WebP for processed output
- resize excessively large source images
- do not retain huge unnecessary originals

Accepted image formats:

- JPEG
- PNG
- WebP

---

# 25. Storage

## 25.1 Public Images

Use local filesystem.

Recommended:

```text
public/uploads/
├── organizations/
│   └── {organization-id}/
│
├── cars/
│   └── {organization-id}/
│       └── {car-id}/
│
└── profiles/
```

Car and organization marketing images may be publicly accessible.

---

## 25.2 Private Files

Sensitive files must live outside `public/`.

Use:

```text
storage/private/
├── documents/
└── inspections/
```

Private files are delivered only through authorized PHP endpoints.

Never expose private file system paths.

---

# 26. Car Browsing

Anonymous browsing is fully supported.

Main routes:

```text
/cars
/agency/{slug}/cars
```

---

## 26.1 Filters

Support:

- agency
- category
- brand
- model
- year
- minimum daily price
- maximum daily price
- transmission
- fuel type
- minimum seats
- pickup location
- pickup date/time
- return date/time

---

## 26.2 Sorting

Support:

- price ascending
- price descending
- newest
- most popular

---

## 26.3 Query Parameters

Filters must persist in URL.

Example:

```text
/cars?brand=Toyota&transmission=automatic&min_price=10&max_price=30
```

Browser:

- refresh must preserve state
- back/forward must behave naturally
- filtered URL must be shareable

---

## 26.4 Pagination

Server-side pagination.

Default:

```text
12 cars per page
```

---

# 27. Car Search

Search must support at minimum:

- brand
- model
- organization name

Search is server-side.

Do not load all cars into the browser and filter client-side.

---

# 28. Car Details

Car detail page displays:

- primary vehicle image
- image gallery
- organization
- brand
- model
- year
- category
- transmission
- fuel type
- seats
- doors
- color
- daily rental rate
- description
- public features
- location
- availability selector
- rental terms
- Book Now CTA
- favorite action for authenticated users

Do not expose publicly:

- VIN
- internal notes
- customer records
- employee notes
- audit history

Plate number should not be displayed publicly by default.

---

# 29. Favorites

Authenticated users may favorite cars.

Table:

```text
favorites
```

Fields:

- user_id
- car_id
- created_at

Unique:

```text
(user_id, car_id)
```

Account page:

```text
/account/favorites
```

---

# 30. Booking Requirements

Booking requires authentication.

Before submission, require:

- authenticated Google user
- phone number
- selected car
- pickup date/time
- return date/time
- pickup location
- return location

---

# 31. Booking Data

Booking fields:

- id
- reference
- organization_id
- user_id
- car_id
- pickup_location_id
- return_location_id
- pickup_at
- return_at
- rental_days
- daily_rate_snapshot_fils
- subtotal_fils
- additional_charges_fils
- total_fils
- status
- payment_status
- cancellation_reason
- cancelled_by_user_id
- cancelled_at
- rejection_reason
- admin_notes
- created_at
- updated_at

---

# 32. Booking References

Do not expose database IDs as public booking references.

Format:

```text
BK-2026-000001
```

Must be unique.

Generation must be concurrency-safe.

---

# 33. Money Handling

Store all money as integer fils.

Examples:

```text
25.500 BHD = 25500 fils
10.000 BHD = 10000 fils
0.500 BHD = 500 fils
```

Never use floating-point columns for money.

Centralize currency formatting.

Default display currency:

```text
BHD
```

Three decimal places.

---

# 34. Rental Day Calculation

Use 24-hour periods.

Formula:

```text
rental_days = max(1, ceil((return_at - pickup_at) / 24 hours))
```

Example:

```text
10 Sep 10:00 → 11 Sep 10:00 = 1 day
10 Sep 10:00 → 11 Sep 13:00 = 2 days
```

Pricing:

```text
subtotal_fils = rental_days × daily_rate_snapshot_fils
```

Booking stores the rate snapshot.

Later changes to car pricing must never alter historical booking prices.

---

# 35. Booking Statuses

Allowed:

```text
pending
confirmed
rejected
ready_for_pickup
active
completed
cancelled
no_show
```

---

# 36. Booking Transitions

Allowed transitions:

```text
pending
├── confirmed
├── rejected
└── cancelled
```

```text
confirmed
├── ready_for_pickup
└── cancelled
```

```text
ready_for_pickup
├── active
├── cancelled
└── no_show
```

```text
active
└── completed
```

Terminal:

```text
completed
rejected
cancelled
no_show
```

Invalid transitions must fail server-side.

Centralize transition validation.

---

# 37. Booking Approval

New booking:

```text
pending
```

No automatic confirmation.

Organization admin or employee with:

```text
bookings.confirm
```

may confirm.

---

# 38. Availability

Pending bookings do not block other bookings.

Blocking booking statuses:

```text
confirmed
ready_for_pickup
active
```

Overlap condition:

```text
existing.pickup_at < requested.return_at
AND
existing.return_at > requested.pickup_at
```

A blocking overlap prevents confirmation.

---

# 39. Concurrent Booking Safety

Booking confirmation must use a database transaction.

Required flow:

1. begin transaction
2. lock relevant car row
3. load overlapping blocking bookings
4. if conflict exists, reject confirmation
5. otherwise confirm booking
6. record audit event
7. commit

Do not trust a previous public availability check.

Final confirmation is authoritative.

---

# 40. Public Availability Search

When dates are supplied:

Exclude cars with blocking booking overlaps.

Also exclude operationally unavailable cars such as:

```text
maintenance
inactive
```

A car currently marked `rented` may still be available for a future requested period if its active booking ends before the requested pickup and no blocking future booking overlaps.

Availability logic must therefore consider both:

- operational state
- requested dates

---

# 41. Customer Cancellation

Customer may cancel own booking only when status is:

```text
pending
confirmed
```

Customer may not cancel:

```text
ready_for_pickup
active
completed
rejected
cancelled
no_show
```

Store:

- cancelled_by_user_id
- cancellation_reason
- cancelled_at

No automated cancellation fee in version 1.

---

# 42. Payment Status

No payment gateway in version 1.

Allowed:

```text
unpaid
paid
refunded
```

Initial payment method:

```text
Pay at pickup
```

Admin or authorized employee may update payment status.

Permission:

```text
bookings.manage_payment
```

---

# 43. Customer Dashboard

Account dashboard should show:

- upcoming booking
- active rental
- pending bookings
- recent booking history
- recent notifications
- document actions if relevant

Keep customer dashboard simple.

Do not overload it with analytics.

---

# 44. Customer Booking Pages

Customer booking sections:

```text
Upcoming
Active
Completed
Cancelled
```

Booking detail includes:

- reference
- agency
- car
- dates
- pickup location
- return location
- booking status
- payment status
- pricing
- cancellation information
- organization contact details where useful

---

# 45. Organization Customers

When a user first books with an organization, create organization-specific customer relationship.

Table:

```text
organization_customers
```

Fields:

- organization_id
- user_id
- internal_notes
- created_at
- updated_at

Unique:

```text
(organization_id, user_id)
```

Organization customer page may show:

- name
- email
- phone
- total bookings
- active booking count
- completed booking count
- document states
- internal notes

Internal notes are never shown to customer.

---

# 46. Pickup Workflow

Permission:

```text
bookings.checkout
```

Booking must be:

```text
ready_for_pickup
```

Capture:

- actual checkout timestamp
- checkout mileage
- checkout fuel percentage
- checkout condition notes
- optional inspection images
- checkout employee

Then:

```text
booking.status = active
car.status = rented
```

Create rental record.

---

# 47. Rental Record

Fields:

- id
- booking_id
- organization_id
- car_id
- customer_user_id
- checkout_employee_user_id
- return_employee_user_id
- checkout_at
- expected_return_at
- actual_return_at
- checkout_mileage
- return_mileage
- checkout_fuel_percentage
- return_fuel_percentage
- checkout_condition
- return_condition
- damage_notes
- additional_charges_fils
- created_at
- updated_at

Unique:

```text
booking_id
```

One booking has at most one rental.

---

# 48. Return Workflow

Permission:

```text
bookings.complete_return
```

Capture:

- actual return timestamp
- return mileage
- return fuel percentage
- return condition
- damage notes
- optional return photos
- additional charges
- return employee

Validation:

```text
return_mileage >= checkout_mileage
```

Then:

```text
booking.status = completed
```

User performing return selects car's next status:

```text
available
maintenance
```

---

# 49. Inspection Images

Inspection images are separate from public car photos.

Fields:

- id
- rental_id
- phase
- file_path
- created_at

Allowed phase:

```text
checkout
return
```

Store privately.

Do not show on public car pages.

---

# 50. Customer Documents

Supported types:

```text
driving_license
national_id
```

Fields:

- id
- user_id
- document_type
- file_path
- expires_at
- created_at
- updated_at

Documents belong to users.

Private storage only.

---

# 51. Organization-Specific Document Review

Verification is organization-specific.

One agency verifying a customer's license does not automatically make it verified for another agency.

Document review fields:

- id
- organization_id
- document_id
- status
- reviewed_by_user_id
- reviewed_at
- rejection_reason
- created_at
- updated_at

Unique:

```text
(organization_id, document_id)
```

Statuses:

```text
pending
verified
rejected
expired
```

Permissions:

```text
documents.view
documents.verify
```

---

# 52. Private Document Access

Private documents:

- must not live under `public/`
- must not have predictable public URLs
- must be streamed by authorized PHP route
- require authenticated user

Allowed viewers:

1. document owner
2. organization admin with valid customer relationship
3. employee with `documents.view` and valid organization/customer relationship

Never allow arbitrary organization employees to load documents from unrelated users.

---

# 53. Notifications

Support:

- in-app notifications
- email notifications

Notification types:

```text
booking_submitted
booking_confirmed
booking_rejected
booking_cancelled
pickup_reminder
return_reminder
rental_overdue
document_verified
document_rejected
employee_invitation
```

Fields:

- id
- user_id
- organization_id, nullable
- type
- title
- message
- related_entity_type
- related_entity_id
- read_at
- created_at

Account route:

```text
/account/notifications
```

Actions:

- mark one as read
- mark all as read

---

# 54. Scheduler

Provide CLI scheduler:

```text
php scripts/scheduler.php
```

Intended to run via cron.

Responsibilities:

- pickup reminders
- return reminders
- overdue checks

Recommended:

```text
hourly
```

Must be idempotent.

Do not create duplicate reminder notifications every scheduler run.

---

# 55. Overdue Rentals

Derived condition:

```text
booking.status = active
AND
current_time > booking.return_at
```

Do not require an `overdue` booking status.

Surface overdue state in:

- management dashboard
- bookings list
- reports
- notifications

---

# 56. Audit Log

Audit at minimum:

- organization created
- organization settings changed
- employee invited
- employee joined
- employee removed
- employee permissions changed
- car created
- car edited
- car archived
- car status changed
- booking confirmed
- booking rejected
- booking cancelled
- payment status changed
- checkout completed
- return completed
- document verified
- document rejected

Fields:

- id
- organization_id
- actor_user_id
- action_key
- entity_type
- entity_id
- metadata_json
- ip_address
- created_at

Audit entries are immutable in normal UI.

---

# 57. Reports

Organization reports:

- total bookings
- pending bookings
- confirmed bookings
- completed bookings
- cancelled bookings
- active rentals
- overdue rentals
- total recorded revenue
- most rented cars
- bookings per car
- bookings by month
- fleet status distribution

Permission:

```text
reports.view
```

Admin always has access.

---

# 58. Organization Dashboard

Show:

- total cars
- available cars
- currently rented cars
- maintenance cars
- today's pickups
- today's returns
- pending bookings
- active rentals
- overdue rentals
- monthly revenue
- recent bookings
- recent activity

All statistics must be scoped to current organization.

---

# 59. Car Management

Organization staff may manage according to permissions:

- create
- edit
- archive
- status
- location
- category
- daily rate
- marketing images

Cars with historical bookings must not be permanently deleted through normal UI.

Use:

- inactive
- archive

Historical references remain intact.

---

# 60. Maintenance Behavior

When status is:

```text
maintenance
```

car must not be offered for current booking.

If future confirmed bookings exist, management UI should display warning before setting maintenance.

A full maintenance work-order system is outside version 1.

---

# 61. Admin Notes

Bookings and organization-customer records may have private organization notes.

Never expose these to customer.

Material note changes should be audit logged.

---

# 62. Database Tables

Implement at minimum:

```text
users
user_profiles

organizations
organization_users

employee_invitations
permissions
employee_permissions
employee_invitation_permissions

locations
car_categories
cars
car_images

favorites

organization_customers

bookings
rentals
rental_inspection_images

user_documents
document_reviews

notifications

activity_logs
```

---

# 63. Important Constraints

Required unique constraints:

```text
users.google_id UNIQUE
users.email UNIQUE

organizations.slug UNIQUE

organization_users
UNIQUE (organization_id, user_id)

permissions.key UNIQUE

employee_permissions
UNIQUE (organization_user_id, permission_id)

employee_invitation_permissions
UNIQUE (invitation_id, permission_id)

car_categories
UNIQUE (organization_id, name)

cars
UNIQUE (organization_id, slug)

favorites
UNIQUE (user_id, car_id)

organization_customers
UNIQUE (organization_id, user_id)

bookings.reference UNIQUE

rentals.booking_id UNIQUE

document_reviews
UNIQUE (organization_id, document_id)
```

---

# 64. Foreign Keys

Use real database foreign keys.

Prefer restrictive deletion behavior for historical business data.

Examples:

- archiving a car must not delete bookings
- removing an employee must not delete audit history
- deleting profile information must not destroy rental records
- deleting an organization should not be implemented as a casual destructive action in version 1

---

# 65. Indexes

Add at minimum:

```text
cars.organization_id
cars.status
cars.brand
cars.category_id
cars.location_id

bookings.organization_id
bookings.user_id
bookings.car_id
bookings.status
bookings.pickup_at
bookings.return_at

organization_users.user_id
organization_users.organization_id

notifications.user_id
notifications.read_at

activity_logs.organization_id
activity_logs.created_at
```

Availability composite index should account for:

```text
car_id
status
pickup_at
return_at
```

---

# 66. Routing

Use a small reusable router.

Public:

```text
GET  /
GET  /cars
GET  /cars/{slug}

GET  /agencies
GET  /agency/{slug}
GET  /agency/{slug}/cars
GET  /agency/{slug}/cars/{carSlug}

GET  /login
GET  /auth/google
GET  /auth/google/callback
POST /logout
```

Customer:

```text
GET  /account
GET  /account/bookings
GET  /account/bookings/{reference}

GET  /account/favorites

GET  /account/profile
POST /account/profile

GET  /account/documents
POST /account/documents
POST /account/documents/{id}/delete

GET  /account/notifications
POST /account/notifications/{id}/read
POST /account/notifications/read-all
```

Booking:

```text
GET  /cars/{slug}/book
POST /cars/{slug}/book

POST /account/bookings/{reference}/cancel
```

Organizations:

```text
GET  /organizations/create
POST /organizations
```

Management:

```text
GET  /manage/{org}
GET  /manage/{org}/dashboard

GET  /manage/{org}/cars
GET  /manage/{org}/cars/create
POST /manage/{org}/cars
GET  /manage/{org}/cars/{id}/edit
POST /manage/{org}/cars/{id}
POST /manage/{org}/cars/{id}/archive

GET  /manage/{org}/categories
GET  /manage/{org}/locations

GET  /manage/{org}/bookings
GET  /manage/{org}/bookings/{reference}

GET  /manage/{org}/customers
GET  /manage/{org}/customers/{id}

GET  /manage/{org}/documents

GET  /manage/{org}/employees
GET  /manage/{org}/employees/invite
POST /manage/{org}/employees/invite
GET  /manage/{org}/employees/{id}/permissions
POST /manage/{org}/employees/{id}/permissions

GET  /manage/{org}/reports
GET  /manage/{org}/activity
GET  /manage/{org}/settings
POST /manage/{org}/settings
```

Exact POST action URLs may be adjusted while preserving clarity and authorization.

---

# 67. CSRF

All state-changing requests require CSRF.

Applies to:

- POST
- simulated PUT/PATCH
- simulated DELETE

Invalid CSRF:

```text
403 Forbidden
```

Centralize CSRF generation and validation.

---

# 68. SQL Security

Use PDO prepared statements for all values derived from request/user input.

Never concatenate user-controlled values into SQL.

---

# 69. HTML Escaping

Escape untrusted values before output using a centralized helper based on:

```php
htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8')
```

Apply to:

- names
- descriptions
- notes
- query values
- organization information
- car descriptions
- user values

---

# 70. Upload Security

Validate:

- request upload success
- maximum file size
- MIME type using `finfo`
- extension
- image decoding for images

Do not trust original filename.

Generate random storage filenames.

---

# 71. Private File Security

Private file delivery route must:

- authenticate
- authorize
- resolve storage path from trusted database value
- prevent `../` traversal
- send correct MIME headers
- send file as attachment or inline according to type

---

# 72. Google OAuth Security

Must use:

- OAuth state
- exact configured redirect URI
- HTTPS in production
- verified email
- server-side identity validation
- client secret only on server

---

# 73. Rate Limiting

Implement simple rate limiting for sensitive routes where practical:

- Google login initiation
- invitation acceptance
- booking submission
- document upload
- organization invite creation

Do not over-engineer distributed rate limiting.

A session/IP/database or file-backed implementation is acceptable for version 1.

---

# 74. Error Handling

Production must not display:

- stack traces
- raw SQL
- credentials
- filesystem paths
- OAuth secrets

Write technical errors to:

```text
storage/logs/
```

Show clean user-facing errors.

---

# 75. Environment Variables

`.env.example` should include:

```text
APP_NAME=Rentivo
APP_ENV=local
APP_URL=
APP_DEBUG=true
APP_TIMEZONE=Asia/Bahrain

DB_HOST=
DB_PORT=3306
DB_DATABASE=
DB_USERNAME=
DB_PASSWORD=

GOOGLE_CLIENT_ID=
GOOGLE_CLIENT_SECRET=
GOOGLE_REDIRECT_URI=

SMTP_HOST=
SMTP_PORT=
SMTP_USERNAME=
SMTP_PASSWORD=
SMTP_ENCRYPTION=
MAIL_FROM_ADDRESS=
MAIL_FROM_NAME=Rentivo
```

`.env` must be ignored.

---

# 76. Time Handling

Business display timezone:

```text
Asia/Bahrain
```

Prefer storing database timestamps in UTC.

Convert to Bahrain timezone for display and business forms.

Use a centralized date/time helper.

---

# 77. Backups

Production backup plan must include:

1. MySQL
2. `public/uploads/`
3. `storage/private/`

Database backup alone is insufficient.

Backup automation may be operational rather than built into application.

---

# 78. Design System

## 78.1 Visual Direction

Rentivo must use a premium, minimal, automotive visual language inspired by the approved references supplied during planning.

The target feel is:

- monochrome
- premium
- editorial
- modern
- spacious
- highly polished
- product-focused
- automotive

Do not make the interface look like:

- Bootstrap defaults
- generic admin templates
- colorful dashboard kits
- university coursework
- glassmorphism-heavy concept UI

The references are inspiration only.

Do not copy:

- logos
- names
- copyrighted photography
- exact compositions
- text

---

# 79. Design Tokens

Create:

```text
public/assets/css/tokens.css
```

Centralize:

- colors
- typography
- spacing
- radius
- shadows
- borders
- animation durations
- breakpoints
- z-index layers

Example conceptual tokens:

```text
--color-bg
--color-surface
--color-text
--color-text-muted
--color-border
--color-accent

--radius-sm
--radius-md
--radius-lg
--radius-xl

--space-1
--space-2
--space-3
...

--font-size-xs
--font-size-sm
--font-size-base
--font-size-lg
--font-size-xl
--font-size-display
```

Avoid random values repeated throughout component CSS.

---

# 80. Color Direction

Platform palette should primarily use:

- near-black
- white
- soft warm/light gray
- neutral borders
- muted gray text

Example direction only:

```text
Near black: #111111
White: #FFFFFF
Background: around #F4F4F2
Surface: around #F8F8F6
Border: around #E5E5E2
Muted text: around #777777
```

Organization primary color is an accent only.

It may be used for:

- active state
- small badge
- primary accent
- storefront highlight

It must not fully re-theme the system.

---

# 81. Typography

Use a modern sans-serif system.

Design direction:

- very strong bold headings
- large display text
- clean compact body copy
- restrained small labels
- strong contrast

Hero headings should be large and automotive/editorial.

---

# 82. Surface and Layout Style

Use:

- light neutral app background
- large white surfaces
- rounded page shells
- thin neutral borders
- subtle shadows
- generous whitespace
- clean image-first cards

Major radius:

```text
approximately 20 to 32px
```

Controls may use smaller radii.

Do not wrap every section in a heavy card.

Whitespace should create hierarchy.

---

# 83. Reusable UI Components

Implement reusable components.

## Layout

- AppShell
- PublicHeader
- PublicFooter
- ManagementSidebar
- ManagementTopbar
- PageHeader
- ContentSection

## Primitives

- Button
- IconButton
- Badge
- Card
- Avatar
- Divider
- Spinner
- Tooltip

## Forms

- TextField
- TextArea
- Select
- Checkbox
- Toggle
- DateTimeField
- SearchField
- PriceRange
- FileUpload

## Navigation

- Tabs
- Breadcrumbs
- Pagination
- DropdownMenu
- SegmentedControl

## Feedback

- Alert
- Toast
- EmptyState
- ErrorState
- ConfirmationModal
- Drawer

## Cars

- CarCard
- CarGrid
- CarGallery
- CarSpecifications
- CarPrice
- CarStatusBadge
- CarFilterPanel
- FavoriteButton

## Booking

- BookingCard
- BookingSummary
- BookingStatusBadge
- BookingTimeline
- PriceBreakdown
- BookingActionPanel

## Organization

- AgencyCard
- AgencyBrandHeader
- EmployeeCard
- PermissionMatrix

## Dashboard

- MetricCard
- ActivityItem
- DashboardSection
- DataTable

Pages compose components.

Do not duplicate component markup.

---

# 84. Car Card

Image-first layout.

Display:

- car image
- favorite icon
- brand + model
- compact metadata
- daily rate
- organization where useful

Do not overload with specifications.

Variants may include:

```text
compact
standard
featured
```

Use same base component.

---

# 85. Public Browse Page

Desktop direction:

```text
┌──────────────┬─────────────────────────────────┐
│ Filters      │ Cars                            │
│              │                                 │
│ Agency       │ [Car] [Car] [Car]               │
│ Price        │ [Car] [Car] [Car]               │
│ Brand        │ [Car] [Car] [Car]               │
│ Category     │                                 │
│ Transmission│                                 │
│ Fuel         │                                 │
│ Seats        │                                 │
│ Dates        │                                 │
└──────────────┴─────────────────────────────────┘
```

Use:

- left filter rail on desktop
- compact search
- clear result count
- sort control
- grid
- strong vehicle imagery

Mobile:

```text
Search

[ Filters ] [ Sort ]

CarCard
CarCard
CarCard
```

Filters open in reusable drawer.

---

# 86. Map View

The approved visual references include a map-heavy browsing experience.

Version 1 must not require a paid map provider.

Therefore:

- Grid View is the required functional V1 experience.
- Design the browse page so a future Grid/Map toggle can be added.
- Do not implement a fake map.
- Do not embed unapproved external map APIs.

---

# 87. Car Details UI

Design direction:

- large gallery
- strong vehicle title
- rate prominently displayed
- compact specs
- booking panel
- tabs/sections for details

Suggested sections:

```text
Overview
Specifications
Features
Rental Terms
Location
```

Desktop:

- large visual area
- booking card/panel remains easy to access

Mobile:

- booking CTA remains obvious
- may use sticky bottom CTA where appropriate

---

# 88. Public Homepage Design

Suggested structure:

```text
Header

Hero
├── Large headline
├── supporting copy
├── Browse Cars CTA
└── premium car imagery

Discovery Strip
├── agencies
├── categories
└── brands

Featured Fleet

How It Works

Platform Features

Featured Agencies

Final CTA

Footer
```

Vehicle photography should dominate the visual experience.

---

# 89. Management UI

Management uses the same design system, with denser information.

Layout:

```text
┌───────────────┬─────────────────────────────┐
│ Organization  │ Topbar                      │
│ logo/name     ├─────────────────────────────┤
│               │                             │
│ Dashboard     │ Main management content     │
│ Cars          │                             │
│ Bookings      │                             │
│ Customers     │                             │
│ Employees     │                             │
│ Reports       │                             │
│ Activity      │                             │
│ Settings      │                             │
└───────────────┴─────────────────────────────┘
```

Do not use an unrelated admin template.

---

# 90. Responsive Design

Public/customer pages:

- excellent mobile UX required

Management:

- desktop optimized
- tablet usable
- phone usable

Tables may:

- horizontally scroll
- collapse to card layout

depending on context.

---

# 91. Interaction Design

Use subtle transitions:

```text
150–250ms
```

Examples:

- button press
- card hover
- image hover
- modal transition
- drawer transition
- filter selection

Respect:

```text
prefers-reduced-motion
```

Avoid distracting motion.

---

# 92. CSS Architecture

Recommended:

```text
public/assets/css/
├── tokens.css
├── base.css
├── layout.css
├── components/
│   ├── buttons.css
│   ├── forms.css
│   ├── cards.css
│   ├── navigation.css
│   ├── car-card.css
│   ├── tables.css
│   ├── modal.css
│   ├── drawer.css
│   └── badges.css
└── pages/
    ├── home.css
    ├── browse-cars.css
    ├── car-details.css
    ├── account.css
    └── management.css
```

Prefer semantic component naming.

Avoid brittle selectors.

---

# 93. JavaScript Architecture

Recommended:

```text
public/assets/js/
├── app.js
└── components/
    ├── modal.js
    ├── drawer.js
    ├── dropdown.js
    ├── filters.js
    ├── gallery.js
    ├── tabs.js
    ├── toast.js
    └── image-upload.js
```

Avoid one giant JavaScript file.

---

# 94. Development Component Gallery

Development-only route:

```text
/dev/components
```

Display examples of:

- buttons
- inputs
- selects
- badges
- alerts
- car cards
- booking cards
- tabs
- tables
- modals
- drawers
- pagination
- empty states
- loading states

Must not be accessible in production.

---

# 95. Accessibility

At minimum:

- semantic HTML
- keyboard-accessible controls
- visible focus states
- sufficient contrast
- labels for form controls
- alt text for meaningful images
- icon buttons must have accessible labels
- dialogs/drawers must manage focus reasonably
- validation errors must be understandable

---

# 96. Public Empty and Error States

Required states:

- no cars found
- no agencies found
- car unavailable
- booking conflict
- no favorites
- no bookings
- no notifications
- failed upload
- permission denied
- invalid invite
- expired invite

Use reusable feedback components.

---

# 97. Installation and Local Development

README must include:

- prerequisites
- PHP version
- MySQL requirements
- Composer install
- `.env` setup
- Google OAuth configuration
- database creation
- migration command
- seed command
- local PHP server example
- SMTP setup
- scheduler setup
- storage permissions
- how to create first organization

---

# 98. Migration System

Provide simple migration runner.

Example:

```text
php scripts/migrate.php
```

Requirements:

- ordered migrations
- migration history table
- migrations run once
- clean failure message
- safe from accidental duplicate execution

Seeds:

```text
php scripts/seed.php
```

Seed:

- permissions
- optional demo categories
- optional development demo content only when explicitly enabled

---

# 99. Implementation Phases

## Phase 1: Foundation

Implement:

- project skeleton
- Composer
- router
- PDO
- migrations
- environment config
- sessions
- CSRF
- base layouts
- error handling
- logging
- component foundation
- design tokens

Acceptance:

Application boots cleanly from fresh clone.

---

## Phase 2: Google Authentication

Implement:

- Google OAuth
- account creation
- login
- logout
- intended destination
- profile foundation

Acceptance:

Google authentication is the only authentication method and works reliably.

---

## Phase 3: Organizations and Permissions

Implement:

- organization creation
- organization membership
- admin role
- employee invitation
- invite matching
- permissions
- permission enforcement
- organization settings

Acceptance:

Two organizations are strictly isolated.

---

## Phase 4: Fleet

Implement:

- locations
- categories
- cars
- car image upload
- image management
- organization branding
- car archival/status

Acceptance:

Each agency manages an independent fleet.

---

## Phase 5: Public Marketplace

Implement:

- homepage
- agency listing
- agency storefront
- browse cars
- search
- filters
- sorting
- pagination
- car details
- date-based availability

Acceptance:

Anonymous visitors can browse fully without login.

---

## Phase 6: Customer Account and Booking

Implement:

- profile completion
- phone requirement
- booking flow
- Google redirect preservation
- booking price snapshot
- references
- customer dashboard
- booking history
- cancellation
- favorites

Acceptance:

Anonymous visitor can pick a car, authenticate, return to checkout, and submit booking.

---

## Phase 7: Organization Booking Management

Implement:

- booking list
- booking detail
- confirm
- reject
- cancel
- transaction-based conflict prevention
- payment status
- customer relationship
- admin notes

Acceptance:

Bookings are securely managed per organization and permission.

---

## Phase 8: Pickup and Return

Implement:

- ready for pickup
- checkout
- rental records
- inspection images
- active rental
- return
- damage notes
- additional charges
- final car state

Acceptance:

Booking can complete the full rental lifecycle.

---

## Phase 9: Documents

Implement:

- private upload
- private delivery
- organization-specific document review
- verify/reject
- expiration handling

Acceptance:

Sensitive customer files are never publicly accessible.

---

## Phase 10: Notifications and Scheduler

Implement:

- in-app notifications
- email notifications
- reminders
- scheduler
- overdue logic

Acceptance:

Lifecycle events generate correct notifications without duplicates.

---

## Phase 11: Reports and Audit

Implement:

- dashboard metrics
- reports
- activity logs
- audit UI

Acceptance:

Organization reporting includes only its own data.

---

## Phase 12: Hardening

Implement/review:

- tenant isolation tests
- authorization tests
- CSRF
- input validation
- upload security
- production errors
- responsive UX
- accessibility
- query indexes
- README
- clean fresh-install test

---

# 100. Critical Acceptance Tests

## Test 1: Public Browsing

Anonymous visitor can:

- open homepage
- browse agencies
- browse cars
- filter
- search
- view car details

No authentication.

---

## Test 2: Authentication Only at Booking

Anonymous visitor selects:

- car
- pickup time
- return time

Presses booking action.

System:

- requires Google login
- returns user to booking checkout
- preserves selection

---

## Test 3: Organization Isolation

Create Organization A and Organization B.

Employee A must not access:

- Organization B cars
- Organization B bookings
- Organization B customers
- Organization B employees
- Organization B reports
- Organization B documents

Even by manually changing URL IDs/slugs.

---

## Test 4: Employee Permission

Employee has:

```text
cars.view
```

but not:

```text
cars.edit
```

Employee:

- can open car list
- cannot open/edit car edit action
- direct request is denied server-side

---

## Test 5: Invitation Email Match

Invite:

```text
employee@example.com
```

Only that verified Google email can accept.

Other Google account cannot accept even with token.

---

## Test 6: Booking Conflict

Two overlapping pending bookings exist for same car.

Confirm booking A.

Confirm booking B.

Expected:

- booking B confirmation fails
- no overlapping confirmed bookings exist

---

## Test 7: Historical Pricing

Car rate:

```text
20.000 BHD/day
```

Booking created.

Car rate later changed:

```text
25.000 BHD/day
```

Booking remains:

```text
20.000 BHD/day snapshot
```

---

## Test 8: Private Documents

Customer uploads license.

Direct public URL cannot access file.

Customer owner can access through protected route.

Authorized organization employee can access only if:

- organization relationship exists
- employee has `documents.view`

---

## Test 9: Booking Lifecycle

Valid:

```text
pending
→ confirmed
→ ready_for_pickup
→ active
→ completed
```

Invalid transition is rejected.

---

## Test 10: Pickup and Return

Checkout:

- creates rental
- captures mileage/fuel
- car becomes rented
- booking active

Return:

- validates mileage
- captures condition
- booking completed
- car becomes available or maintenance

---

## Test 11: Money

All persisted monetary amounts are integers.

No booking price column uses float/double.

---

## Test 12: CSRF

State-changing request without valid CSRF token returns 403.

---

# 101. Coding Standards for Claude Code

Claude Code must:

- use Composer autoloading
- use namespaces
- use strict typing where practical
- use small focused classes
- use services for business logic
- use repositories for SQL/data access
- use prepared statements
- use transactions where required
- keep SQL out of view templates
- keep authorization centralized
- keep CSRF centralized
- keep reusable UI components
- avoid duplicated markup and logic
- escape output
- validate all external input
- create clear README
- create migrations
- create tests for critical workflows

---

# 102. Claude Code Restrictions

Claude Code must NOT:

- replace plain PHP with Laravel
- introduce another full-stack framework
- introduce React/Vue/Angular
- introduce Node backend
- use Firebase
- use S3
- introduce payment gateway
- add password auth
- invent new global roles
- weaken tenant isolation
- authorize only through hidden buttons
- store private documents publicly
- store money as floating point
- allow employees to edit permissions
- auto-confirm bookings
- let pending bookings permanently block availability
- skip transaction protection when confirming booking
- copy branding or exact layouts from visual references
- over-engineer infrastructure

---

# 103. Definition of Done

Version 1 is complete when:

- Google-only authentication works
- public car browsing requires no account
- organizations can be created
- each organization has own branding
- each organization has own fleet
- organization admin has full authority
- admin can invite employees
- employee permissions work server-side
- tenant isolation is verified
- categories work
- locations work
- car CRUD and archival work
- local image storage works
- booking flow works
- booking conflicts are prevented
- booking price snapshots work
- customer dashboard works
- favorites work
- pickup workflow works
- return workflow works
- private documents work
- organization-specific document review works
- notifications work
- scheduler works
- overdue logic works
- audit logs work
- reports work
- BHD integer money works
- responsive design works
- reusable UI component system is used
- component gallery exists in development
- `.env` is not committed
- README supports clean installation
- a fresh database can be fully created through migrations
- critical acceptance tests pass

---

# 104. Product Identity

**Working Product Name:** Rentivo

**Positioning:**  
A premium multi-agency car rental marketplace and management platform.

**Brand personality:**

- modern
- premium
- confident
- clean
- efficient
- automotive
- trustworthy

The name is a working product name for implementation. Domain and trademark availability should be verified before commercial launch.

---

# 105. Final Instruction to Claude Code

Treat this SRS as the source of truth.

Build Rentivo incrementally by phase.

Do not attempt to implement the entire system as one giant script.

Before each phase:

1. inspect the existing implementation
2. reuse established components
3. preserve tenant isolation
4. preserve design-system consistency
5. add/update tests
6. avoid regressions
7. update README where setup changes
8. keep implementation simple

If a requirement is unclear, choose the solution that is:

- safest
- simplest
- reusable
- organization-isolated
- consistent with the existing component architecture
- consistent with the premium Rentivo visual design

Do not introduce major new product behavior without explicit approval.
