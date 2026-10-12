# Verification record

Local verification on 12 October 2026 used PHP 8.5.11 and a separate MySQL 9.5 instance bound to loopback. The implementation targets PHP 8.1+ and MySQL 8+; the CI definition provides a PHP 8.3 / MySQL 8.0 check but its remote run has not yet been verified.

## Completed

- Every application, configuration, setup and test PHP file passed `php -l`.
- `tests/integration.py`: **60 checks passed** against the running PHP application and real MySQL database.
- Public pages returned HTTP 200 and unknown packages returned 404.
- Tested customer registration and duplicate-email handling, login/logout, CSRF rejection, staff/admin access restrictions and server-side role assignment.
- Tested trusted prices, invalid dates/party sizes, departure capacity, duplicate form handling, cancellation ownership, released capacity and booking transition restrictions.
- Tested package create/edit/archive/restore/delete and prevention of deletion when bookings exist.
- Tested escaped enquiry content, customer isolation and staff replies.
- Tested administrator self-lockout protection, immediate role updates, disabled sessions, report date validation and CSV downloads.
- Browser DOM inspection verified the responsive navigation at a 390-pixel viewport, the mobile content structure and the complete experience selector.

## Limits

Browser screenshot capture was unavailable in the local preview tool. Pixel-level visual review and full interactive mobile acceptance testing are therefore not claimed. Docker was supplied as a reproducible alternative but was not executed locally. No production deployment, payment processing, external email delivery, real-user UAT, load test or independent penetration test was performed.

The HTTP suite exercises capacity sequentially; it is not a concurrent load test. Row locking is used to serialize booking creation, but production load and infrastructure behavior should be tested separately. Remote image availability depends on the image host and connectivity.

## Suggested human acceptance checks

1. Open the homepage on desktop and a phone; inspect imagery, typography, menu behavior and form focus.
2. Register a customer, search for a trip and submit a request.
3. Sign in as staff, review the booking and reply to an enquiry.
4. Confirm the customer sees their updated status and private reply.
5. Sign in as administrator, manage a staff role and download a report.
6. Confirm the catalogue content, prices and itineraries with the tour operator before launch.
