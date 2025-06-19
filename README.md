## Post Sync Plugin 

> [!IMPORTANT]
>Automatically syncs WordPress posts and user data with a Next.js server whenever a post or user is created, updated, deleted, or restored. Supports custom post types and includes secure API key integration for communication.

1. 🔁 **Auto Sync on Events**
It automatically syncs posts and users with a Next.js server on creation, update, deletion, or restoration—no manual actions required.

2. 🧠 **Supports Custom Post Types**
Built specifically for news, article, and announcement post types with tailored payload structures for each.

3. 🔐 **Secure Communication**
All sync requests use a secure API key in the header, ensuring only authorized connections with the Next.js server.

4. 📦 **Rich, Structured Payloads**
Sends detailed JSON data including titles, content, tags, images, author names, roles, and even custom ACF fields like position.

5. 🛠️ **Error Logging for Debugging**
Built-in logging via error_log() helps track sync events and quickly identify any issues during data transfer.

---
**The List of Plugins Being Used in the wordpress site is**
| Serial Number | Plugin Name                     | Version | Author                |
|---------------|---------------------------------|---------|-----------------------|
| 1             | [Advanced Custom Fields](https://wordpress.org/plugins/advanced-custom-fields/) | 6.3.12  | WP Engine             |
| 2             | [Classic Editor](https://wordpress.org/plugins/classic-editor/) | 1.6.7   | WordPress Contributors |
| 3             | [Members](https://wordpress.org/plugins/members/) | 3.2.18  | MemberPress           |
| 4             | [Post and User Sync Plugin](https://github.com/SuyogPrasai/parewa_cms/releases/tag/parewa) | 1.62    | Suyog Prasai    |
| 5             | [Ultimate Dashboard](https://wordpress.org/plugins/ultimate-dashboard/) | 3.8.9   | David Vongries        |