## Post Sync Plugin 

>![Important]
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