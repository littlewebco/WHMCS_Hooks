# WHMCS → n8n Webhook Template (JWT-Signed)

Forward WHMCS client events (create, update, delete) to **n8n** or any
other webhook consumer, with payloads protected by JSON Web Tokens (JWT).

---

## Features

* Hooks into `ClientAdd`, `ClientEdit`, and `ClientDelete`.
* Sends a minimal JSON payload plus an `Authorization: Bearer <JWT>`
  header so the receiver can verify authenticity and integrity.
* Fully self-contained – drop into `whmcs/includes/hooks/`.
* MIT-licensed and ready to extend.

---

## 1 – Installation

1. **Copy the hook file**

   ```bash
   cp notifyWebhooks.php /path/to/whmcs/includes/hooks/
   ```

2. **Set your webhook URL**

   Replace every `REPLACE_WITH_WEBHOOK_URL_HERE` constant with the HTTPS
   endpoint of the *Webhook* node in your n8n workflow.

3. **Configure the secret**

   ```php
   // notifyWebhooks.php
   const JWT_SECRET_KEY = getenv('WHMCS_JWT_SECRET');
   ```

   Then define the environment variable on the server:

   ```bash
   export WHMCS_JWT_SECRET="$(openssl rand -hex 32)"
   ```

   Never commit the real key to Git.

---

## 2 – Creating the n8n Workflow

1. **Add a “Webhook” trigger**

   * HTTP Method: `POST`
   * Authentication: `None` (we’ll verify manually)
   * Click *Save* to generate the URL; use this in the PHP hook file.

2. **Verify the JWT**

   Add a *Function* node right after the Webhook:

   ```javascript
   // Function node – “Verify JWT”
   const jwt = require('jsonwebtoken');
   const secret = $env.WHMCS_JWT_SECRET;   // Same key as WHMCS

   try {
     const token = $headers.authorization.replace('Bearer ', '');
     const decoded = jwt.verify(token, secret, { algorithms: ['HS256'] });
     // Expose the payload to later nodes
     return [{ json: decoded }];
   } catch (err) {
     throw new Error('Invalid JWT: ' + err.message);
   }
   ```

   *Alternatively*, you can use the [JWT node](https://n8n.io/integrations/n8n-nodes-base.jwt)
   if installed.

3. **Process the data**

   From here you can:
   * Store the client in a database
   * Post to Slack
   * Trigger additional workflows
   * …and anything else n8n supports.

---

## 3 – Payload Reference

| Event        | Key        | Description                         |
|--------------|-----------|-------------------------------------|
| ClientAdd    | clientId   | WHMCS client ID                     |
|              | firstname  | Client’s first name (if provided)   |
|              | lastname   | Client’s last name (if provided)    |
|              | email      | Email address                       |
| ClientEdit   | (same as above) + `action: "updated"`            |
| ClientDelete | clientId, `action: "deleted"`                    |

---

## 4 – Security Tips

* Always use HTTPS for webhooks.
* Store `JWT_SECRET_KEY` outside the web-root and git history.
* Rotate the secret periodically and update both WHMCS & n8n.
* Keep WHMCS and n8n up-to-date with security patches.

---

## 5 – License

MIT – see [LICENSE](LICENSE) for details. Feel free to fork and improve!
