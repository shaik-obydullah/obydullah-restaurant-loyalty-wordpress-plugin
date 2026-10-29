<p align="center">
  <img src="docs/Banner.jpeg" alt="Obydullah Restaurant Loyalty" width="100%">
</p>

<h1 align="center">Obydullah Restaurant Loyalty</h1>

<p align="center">
  <a href="https://wordpress.org/plugins/obydullah-restaurant-loyalty/"><img src="https://img.shields.io/badge/WordPress-6.2%2B-21759B?logo=wordpress&logoColor=white" alt="WordPress 6.2+"></a>
  <a href="https://www.php.net/"><img src="https://img.shields.io/badge/PHP-8.0%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.0+"></a>
  <a href="https://www.mysql.com/"><img src="https://img.shields.io/badge/MySQL-5.7%2B-4479A1?logo=mysql&logoColor=white" alt="MySQL"></a>
  <a href="https://www.gnu.org/licenses/gpl-2.0.html"><img src="https://img.shields.io/badge/License-GPLv2%20or%20later-blue.svg" alt="License: GPLv2 or later"></a>
  <a href="https://wordpress.org/plugins/obydullah-restaurant-loyalty/"><img src="https://img.shields.io/wordpress/plugin/v/obydullah-restaurant-loyalty?label=version" alt="WordPress.org version"></a>
  <a href="https://wordpress.org/plugins/obydullah-restaurant-loyalty/"><img src="https://img.shields.io/wordpress/plugin/dt/obydullah-restaurant-loyalty?label=downloads" alt="Downloads"></a>
</p>

<p align="center">
  <strong>Restaurant loyalty program with points, stamps, or visit tracking, staff-recorded transactions, rewards, and a member portal.</strong>
</p>

<p align="center">
  <a href="https://wordpress.org/plugins/obydullah-restaurant-loyalty/">
    <img src="https://img.shields.io/badge/%E2%AC%87%20Download-Official%20WordPress.org%20Release-21759B?style=for-the-badge&logo=wordpress&logoColor=white" alt="Download the official release from WordPress.org">
  </a>
</p>

---

## 📖 Description

**Obydullah Restaurant Loyalty** turns your site into a restaurant loyalty program. It runs entirely inside WordPress: **no WooCommerce, no POS integration, and no external service.**

The plugin supports three interchangeable program modes:

| Mode | How it works |
| --- | --- |
| 🪙 **Points** | Members earn spendable value, with a configurable earning rate and a configurable cash value per point. |
| 🎟️ **Stamps** | A member collects a stamp per qualifying visit or spend threshold, and spends stamps on rewards. |
| 📅 **Visits** | Members accumulate visit counts and spend them on rewards. |

A restaurant runs one mode at a time and can change it later. Every reward can cost points, stamps, or visits, independently of the program mode.

### 🧑‍🍳 Staff tools

- 📊 Dashboard with balance, redemption, and enrolment summaries.
- 🧾 A single **Record** screen for purchases, manual adjustments, and redemption, with member search and member card scanning.
- 👥 Member management with member codes, QR cards, birthdays, referral codes, notes, and soft delete.
- 🎁 Reward management for fixed-value, percentage, and free-item rewards, with stock limits, per-member limits, and minimum purchase.
- 🕘 Redemption history, activity logs, and CSV export.
- 🎂 Automatic birthday rewards and referral tracking.

### 🙋 Member portal

Members sign in to a portal page and can:

- 💰 View their current balance, stamps, visits, and member-since date.
- 🧾 View recent transaction history.
- 🔓 See which rewards they are currently eligible for and why the others are locked.
- 🎫 Claim an eligible reward and receive a redemption code to present at the counter.
- ✏️ Update their own name, phone, and birthday.

Members can never change their status, notes, or the email address that links their account. Both self-service actions can be turned off independently from the settings screen.

### 🪪 Member cards

Each member gets a unique member code, referral code, and QR token. The QR resolves to a staff-only lookup: scanning a card as a staff member opens the Record screen with that member preselected, while anyone without the record permission is redirected away and the token is never resolved.

### 🔒 Data integrity

Balances are never edited directly. Every change is an append-only row in the member transactions ledger, and the cached member totals are rebuilt from that ledger. A purchase can never be recorded twice for the same external reference, and reward stock is reserved atomically so the last unit cannot be oversold.

### 🛡️ Privacy

- The member portal page and the card scan endpoint are marked `noindex`, so member balances and live card tokens are kept out of search results.
- Export actions are capability checked and the portal only ever resolves the signed-in member's own record.
- Uninstalled data is preserved by default. Administrators opt in to a full purge from the privacy settings.
- Uninstalling removes the plugin roles and the scheduled maintenance event either way.

---

## 🚀 Installation

1. Upload the `obydullah-restaurant-loyalty` folder to `/wp-content/plugins/`, or install the zip from **Plugins → Add New**.
2. Activate the plugin. Activation creates the database tables, the plugin roles, the default settings, the daily maintenance event, and a member portal page.
3. Visit **Loyalty → Settings** to choose the program mode, earning rules, and label wording.
4. Visit **Loyalty → Rewards** to create your first reward.
5. Staff with the Manager or Staff role can enrol members and record transactions.

> **Or install the official release:** <https://wordpress.org/plugins/obydullah-restaurant-loyalty/>

---

## ❓ Frequently Asked Questions

### Does this need WooCommerce or a POS?

No. Transactions are recorded from the Record screen by staff, or through the member portal. Nothing outside WordPress is required.

### Can a member redeem a reward without asking staff?

Yes, if **"Members can claim rewards in the portal"** is enabled. Claiming debits the balance and reserves stock immediately, and issues a code the member shows at the counter. Turn the setting off to require a staff member for every redemption.

### What is the difference between points, stamps, and visits?

Points are a spendable balance that can be configured per unit of currency. Stamps accumulate per qualifying purchase or visit. Visits accumulate one per recorded visit. The program mode decides which of these the staff interface emphasises, but reward costs are configured per reward.

### Are balances safe from manual edits?

Yes. Every point, stamp, and visit change is a ledger row, including manual adjustments. The totals on the member record are a cache that can always be rebuilt from the ledger.

### What happens on uninstall?

By default your member, transaction, reward, and redemption data is kept, so reinstalling restores the program. If you enable **"Delete all data on uninstall"** first, the tables, roles, scheduled event, and plugin data markers are all removed. Plugin-created member accounts and the plugin's own user meta are only deleted in the purge case, and only for users who have no content of their own.

---

## 🖼️ Screenshots

1. Loyalty dashboard with balance, redemption, and enrolment summaries.
2. The Record screen for purchases, adjustments, and redemption.
3. Reward management with stock, per-member limits, and minimum purchase.
4. The member portal showing balance, eligible rewards, and recent activity.
5. The member card with its QR code.

---

## 📥 Download

| Source | Link |
| --- | --- |
| 🌐 **WordPress.org (official)** | <https://wordpress.org/plugins/obydullah-restaurant-loyalty/> |
| 🐙 **GitHub** | <https://github.com/shaik-obydullah/obydullah-restaurant-loyalty-wordpress-plugin> |

---

## 📝 Changelog

### 1.0.0

- Initial release.

---

## 📄 License

Released under the [GPLv2 or later](https://www.gnu.org/licenses/gpl-2.0.html).

Author: [Shaik Obydullah](https://obydullah.com)
