# Firebase Cloud Messaging (FCM) Setup Guide

## Overview
This guide will help you set up Firebase Cloud Messaging for sending push notifications to both the web dashboard (admin) and Flutter app (drivers).

## Step 1: Get Firebase Service Account Credentials

1. Go to [Firebase Console](https://console.firebase.google.com/)
2. Select your project: **wearehousex-35d78**
3. Click on the **gear icon** (⚙️) next to "Project Overview"
4. Select **"Project settings"**
5. Go to the **"Service accounts"** tab
6. Click **"Generate new private key"** button
7. A JSON file will be downloaded (e.g., `wearehousex-35d78-firebase-adminsdk-xxxxx.json`)

## Step 2: Place the Credentials File

1. Rename the downloaded file to: `firebase-credentials.json`
2. Place it in: `backend/storage/app/firebase-credentials.json`

   **OR**

3. Set the path in your `.env` file:
   ```
   FIREBASE_CREDENTIALS_PATH=/full/path/to/your/firebase-credentials.json
   ```

## Step 3: Verify the Setup

After placing the file, you can verify it's working by:

1. **Check the diagnostic endpoint** (requires admin authentication):
   ```
   GET /api/admin/firebase/status
   ```

2. **Check Laravel logs**:
   ```
   backend/storage/logs/laravel.log
   ```
   Look for: "Firebase Messaging initialized successfully"

## Step 4: Verify File Permissions

Make sure the file is readable by the web server:
- **Linux/Mac**: `chmod 644 backend/storage/app/firebase-credentials.json`
- **Windows**: Right-click → Properties → Security → Ensure "Read" permission is granted

## Troubleshooting

### Error: "Firebase credentials file not found"
- Verify the file exists at the path shown in the error
- Check file permissions
- Verify the path in `backend/config/firebase.php` or `.env`

### Error: "Firebase credentials file is not valid JSON"
- Open the file and verify it's valid JSON
- Make sure you downloaded the complete file (not truncated)

### Error: "Firebase Messaging service is not initialized"
- Check Laravel logs for specific error messages
- Verify the service account has FCM permissions in Firebase Console
- Ensure the project ID in the credentials matches your Firebase project

## Important Notes

- **Never commit** the `firebase-credentials.json` file to Git
- The file should be in `.gitignore`
- Keep the credentials secure - they provide full access to your Firebase project
- If credentials are compromised, regenerate them immediately in Firebase Console

## File Structure

```
backend/
├── storage/
│   └── app/
│       └── firebase-credentials.json  ← Place the file here
├── config/
│   └── firebase.php                   ← Configuration file
└── .env                                ← Optional: Set FIREBASE_CREDENTIALS_PATH here
```

## Testing

Once set up, test by:
1. Sending a notification from the admin dashboard to a driver
2. Creating a stock request from Flutter app (should notify admin)
3. Approving/rejecting a stock request (should notify driver)
