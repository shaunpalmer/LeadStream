# LeadStream: Universal Event & Pixel Injector

![LeadStream Header](https://raw.githubusercontent.com/shaunpalmer/LeadStream/main/assets/LeadStream.png)

**Contributors:** shaunpalmer  
**Tags:** analytics, conversion tracking, pixels, events, facebook, tiktok, ga4, wpforms, contact form 7, gravity forms, lead generation, marketing, pixel  
**Requires at least:** 5.0  
**Tested up to:** 6.5  
**Stable tag:** 1.0  
**License:** GPLv2 or later  
**License URI:** https://www.gnu.org/licenses/gpl-2.0.html

---

## Description

**See Which Placement Actually Converts**

Track leads by exact source: site button, printed QR code, Facebook link, YouTube QR, and more.

LeadStream is the all-in-one event tracking and pixel injector that finally puts you in control of your marketing data. No more guessing which forms convert, which page sections win, or which pixels are firing—LeadStream lets you track, label, and optimize every conversion, everywhere.

Supports:
- **Google Analytics (GA4)**
- **Meta / Facebook Pixel**
- **TikTok Pixel**
- **Triple Whale**
- Major WordPress form plugins (**WPForms**, **Contact Form 7**, **Gravity Forms**, **Ninja Forms**) and generic HTML forms

### Highlights

- **Point-and-click event tracking:** starter scripts for forms, buttons, links, phone/email clicks, scrolls, and more.
- **Works with every form builder:** track WPForms, Contact Form 7, Gravity Forms, Ninja Forms, and any generic HTML form.
- **Multi-platform by design:** send events to GA4, Facebook, TikTok, Triple Whale—just check the box.
- **Email notifications:** get instant alerts when leads submit forms, and send automatic thank-you emails to your leads.
- **Fully editable:** all tracking code is visible and customizable.
- **Admin-friendly UI:** load samples with a click and get real-time admin warnings.
- **No data lock-in:** your data stays on your site—no cloud sync, no hidden fees.

Stop guessing, start tracking. Get agency-grade analytics, for free.

---

## Installation

1. Upload the plugin files to `/wp-content/plugins/leadstream-analytics-injector`, or install the plugin through the WordPress plugins screen.
2. Activate the plugin through the **Plugins** screen in WordPress.
3. Go to **LeadStream** in your WordPress admin menu to set up your tracking and starter scripts.
4. Select your platforms and event samples, then click **Load Starter Script** to instantly get ready-to-go tracking code.
5. Paste in your own scripts or tweak the provided samples as needed.
6. (Optional) Configure email notifications in the **Settings** tab to receive alerts when leads submit forms.

---

## Email Notifications

LeadStream can automatically send email notifications when someone submits a form on your site.

### Admin Notifications

Enable admin notifications to receive an email alert each time a lead is captured. The notification includes:
- Lead's email address (if provided)
- Form name and source
- Submission timestamp

Configure the recipient email address in the Settings tab. By default, notifications are sent to your WordPress admin email.

### Lead Auto-Reply

Optionally send an automatic thank-you email to leads who provide their email address. You can customize:
- Subject line
- HTML message body (with plain-text fallback)
- From name and email address

### Supported Forms

Email notifications work automatically with:
- **WPForms** - Extracts email from email field types
- **Contact Form 7** - Detects common email field names
- **Gravity Forms** - Captures email from email fields
- **Ninja Forms** - Extracts email from email field types

### Configuration

1. Go to **LeadStream → Settings** in your WordPress admin
2. Enable admin notifications and/or lead auto-reply
3. Customize the email settings as needed
4. Use the test email feature to verify your configuration

---

## FAQ

### Will this slow down my site?

No. LeadStream only injects the scripts and events you choose. There’s no bloat, no heavy libraries, and no extra cloud requests.

### What analytics and ad platforms are supported?

Google Analytics (GA4), Meta/Facebook Pixel, TikTok Pixel, Triple Whale, and any custom pixel or event handler you want to add.

### Which forms are supported?

WPForms, Contact Form 7, Gravity Forms, Ninja Forms, and any generic HTML form—plus custom events for any other plugin you need.

### Does this collect or send my data anywhere?

Never. All tracking is 100% on your site. No data is sent to third-party servers (except to your own analytics/ad platforms when your scripts fire).

### What if I already use Google Site Kit or another analytics plugin?

LeadStream will warn you about potential double-tracking and help you avoid duplicate events.

### How do I set up email notifications?

Go to **LeadStream → Settings** in your WordPress admin. Enable the notifications you want and customize the email templates. You can test your configuration by sending a test email from the Settings page.

### Will I get email notifications for every form submission?

Yes, if you enable admin notifications. The plugin captures submissions from WPForms, Contact Form 7, Gravity Forms, and Ninja Forms automatically. If the lead provides an email address and you've enabled auto-reply, they'll also receive a thank-you message.

---

## Screenshots

1. Admin panel: Select your forms and pixels, load starter scripts, and tweak your code—all in one screen.
2. Event samples for every major form builder and platform.
3. Admin warnings for potential conflicts or setup mistakes.

---

## Changelog

### 1.0

- Initial release: Universal event tracking, pixel injection, multi-platform support, advanced admin UI, idiot-proof setup, and tracking samples for all major forms.

---

## Upgrade Notice

### 1.0

First public release—feature complete, production-ready, and extensible for any agency or marketer.

---

## Credits

Created by **Shaun Palmer**. Inspired by real-world marketing problems and built for the pros who care about conversion data.
