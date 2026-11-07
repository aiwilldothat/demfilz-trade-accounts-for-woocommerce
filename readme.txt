=== Demfilz Trade Accounts for WooCommerce ===
Contributors: demfilz
Tags: trade account, pay on account, net 30, invoice payment, wholesale
Requires at least: 6.2
Tested up to: 7.1
Stable tag: 1.0.2
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Let approved trade customers pay on account: payment terms, purchase order numbers, open invoices with due dates and payment recording.

== Description ==

Wholesale and trade stores sell to approved business customers on credit: the goods ship now and the invoice is paid within 7, 14 or 30 days, usually by bank transfer. This plugin adds that to WooCommerce.

Approved customers see a **Pay on account** option at checkout. The order goes through without a payment, ships as usual, and is recorded on the customer's account with a due date and their purchase order number. You see what every account owes and what is overdue, and you record each payment when the money arrives.

**At checkout**

* "Pay on account" appears only for logged in customers whose trade account is approved. Nobody else can use it, including through the Store API.
* The box shows the customer's terms ("Payment is due 30 days from the order date") and asks for a purchase order number: optional, required, or not at all.
* Works on the classic shortcode checkout and in the checkout block, with no JavaScript needed on the classic page.
* The order goes to Processing (so it ships), or On hold or Completed if you prefer, and is not marked as paid until you record the payment.
* The thank you page, the order emails and My Account say when payment is due and how to pay (your bank details, from the settings).

**Accounts and applications**

* Customers apply from My Account > Trade account (business name, ABN or tax id, phone, message), or from any page with the `[dtaw_apply]` shortcode. You are emailed; they are emailed when you decide.
* Approve, decline, suspend or reinstate accounts from WooCommerce > Trade Accounts, from the customer's user profile, or from the link in the application email.
* Payment terms per customer, or a store default. An account reference (their account number in your books). Internal notes.
* Roles that are approved automatically, for stores that already give wholesale customers a role.
* Add and approve a customer yourself, without an application.

**Invoices and payments**

* Every order placed on account is an invoice with a due date. The Invoices tab lists unpaid, overdue, paid and void invoices, searchable by order number, purchase order number or email, with a CSV export.
* Record a payment on the order (date, reference, note) or with one click from the Invoices tab. The order gets its paid date and a note; the customer sees it as paid.
* Balance owing, overdue amount, amount due in the next 7 days and open invoice count, for the whole store and per account.
* Cancelled, refunded or failed orders drop off the balance automatically; partial refunds reduce the amount owing.
* An "On account" column on the orders list: due date, days overdue, paid, with the PO number.

**For the customer**

* My Account > Trade account: status, terms, balance owing, open invoices with due dates and overdue flags, recently paid invoices and your payment instructions.

**Sensible defaults**

* 30 day terms, Processing for orders on account, purchase order number optional, no applications blocked.
* Works with WooCommerce High-Performance Order Storage (HPOS) and the cart and checkout blocks.
* Personal data exporter and eraser for the application details; invoices stay with the orders as financial records.
* No external services. Nothing leaves your site.

**For developers**

Actions: `dtaw_status_changed`, `dtaw_order_placed`, `dtaw_payment_recorded`, `dtaw_payment_reversed`, `dtaw_order_box_after`, `dtaw_account_after_status`, `dtaw_account_form_rows`, `dtaw_account_form_save`, `dtaw_admin_account_after`, `dtaw_user_panel_rows`, `dtaw_user_panel_save`, `dtaw_admin_tab_{tab}`.

Filters: `dtaw_is_approved`, `dtaw_terms_days`, `dtaw_due_date`, `dtaw_can_place_on_account`, `dtaw_gateway_available`, `dtaw_checkout_extra_text`, `dtaw_open_statuses`, `dtaw_admin_tabs`, `dtaw_admin_tiles`, `dtaw_account_endpoint`.

Orders placed on account carry the meta `_dtaw_on_account` (1), `_dtaw_state` (open, paid or void), `_dtaw_due` (Y-m-d), `_dtaw_terms` (days), `_dtaw_po`, and once paid `_dtaw_paid_at` and `_dtaw_paid_ref`.

== Installation ==

1. Install and activate the plugin. WooCommerce must be active.
2. WooCommerce > Settings > Payments: make sure "Pay on account" is enabled, and set its title and description.
3. WooCommerce > Trade Accounts > Settings: default terms, the status for orders on account, the purchase order field and your payment instructions.
4. Approve a customer (Accounts tab > Add a customer), or wait for applications from My Account > Trade account.

== Frequently Asked Questions ==

= Who sees "Pay on account"? =

Only logged in customers whose trade account is approved, or whose role is on your auto approved list. Suspended or declined accounts never see it. The check runs again when the order is placed, so it cannot be bypassed by editing the checkout page.

= What happens to the order? =

It is created like any other order and moved to the status you chose (Processing by default), so it ships straight away. The order's paid date stays empty until you record the payment, so reports and accounting syncs do not treat it as paid.

= How do I record a payment? =

Open the order and use the Trade account box (date, reference, note), or click "Paid today" on the Invoices tab. "Mark as unpaid" reverses a mistake.

= Can a customer pay an open invoice by card? =

Not from this plugin: the order was accepted on account, so it is not pending payment. Customers pay by bank transfer following your payment instructions, and you record the payment. Partial payments are not tracked; record the payment when the invoice is paid in full.

= Does it work with the checkout block? =

Yes. The terms, the purchase order field and the "Place order on account" button appear in the checkout block, and the classic shortcode checkout is supported too.

= Can I give different customers different terms? =

Yes. Each account can have its own terms in days; accounts without their own use the store default.

= Is there a Pro version? =

Yes. Demfilz Trade Accounts Pro adds credit limits with checkout blocking, overdue reminders, account statements with aged balances, statement emails and accounting sync hooks (Demfilz Sync for Xero, QuickBooks and MYOB). Everything described on this page works without it.

= Does it work with HPOS? =

Yes, with both order storage modes.

== Screenshots ==

1. WooCommerce > Trade Accounts: balances, the account list and actions.
2. An account: application details, terms, reference and its invoices.
3. "Pay on account" in the checkout block with the purchase order field.
4. The Invoices tab with due dates and one click payment recording.
5. The Trade account box on an order.
6. My Account > Trade account: open invoices and how to pay.
7. The application form.
8. Settings.

== Changelog ==

= 1.0.2 =
* The WP Rocket JavaScript exclusion now follows the site's plugins directory instead of assuming /wp-content/plugins/, so it also works where the content or plugins folder has been moved.

= 1.0.1 =
* The WooCommerce dependency message and action results now appear only in the plugin's own screens, never as dashboard-wide admin notices.

= 1.0.0 =
* First release.

== Upgrade Notice ==

= 1.0.2 =
No data changes. The WP Rocket exclusion now works on sites with a custom plugins directory.

= 1.0.1 =
No data changes. Messages now appear only in the plugin's own screens.

= 1.0.0 =
First release.
