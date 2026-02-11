# Telegram Batch Download

A PHP-based tool for batch downloading media files from Telegram channels, groups, or chats using the MadelineProto library.

## What is This Project?

This project allows you to automatically download all media files (documents, images, videos, etc.) from a specific Telegram channel, group, or chat. It uses the MadelineProto library to interact with Telegram's API and supports:

- **Batch downloading**: Downloads media in batches to avoid API limits
- **Resume capability**: Saves progress and can resume from where it left off if interrupted
- **Automatic retry**: Handles download failures with automatic retries
- **File verification**: Verifies downloaded files by comparing file sizes
- **Session management**: Automatically handles and resets Telegram sessions when needed

## Prerequisites

- **PHP 7.4 or higher** (PHP 8.0+ recommended)
- **Composer** (PHP dependency manager)
- **Extensions**: The following PHP extensions are required by MadelineProto:
  - `mbstring`
  - `xml`
  - `json`
  - `fileinfo`
  - `curl` or `fopen` for HTTP requests
  - `openssl` for encryption
- **Telegram API credentials** (API ID and API Hash)
- **A Telegram account** to authenticate with

## Installation

### 1. Clone the Repository

```bash
git clone https://github.com/sonwal/telegram_batch_download.git
cd telegram_batch_download
```

### 2. Install Dependencies

Install the required PHP packages using Composer:

```bash
# If you don't have composer installed, download it first
php -r "copy('https://getcomposer.org/installer', 'composer-setup.php');"
php composer-setup.php
php -r "unlink('composer-setup.php');"

# Install dependencies
php composer.phar install
# OR if composer is globally installed:
composer install
```

This will install MadelineProto and all its dependencies in the `vendor/` directory.

## Configuration

### Obtaining Telegram API Credentials

Before you can use this tool, you need to obtain API credentials from Telegram:

1. **Visit Telegram's API Development Tools**: Go to [https://my.telegram.org/apps](https://my.telegram.org/apps)

2. **Log in** with your Telegram phone number

3. **Create a new application**:
   - Enter an "App title" (e.g., "Batch Downloader")
   - Enter a "Short name" (e.g., "batch_dl")
   - The platform can be left as "Desktop"
   - Click "Create application"

4. **Copy your credentials**:
   - **API ID**: A numeric value (e.g., 1234567)
   - **API Hash**: A string of letters and numbers (e.g., "abcd1234efgh5678...")

### Configuring the Script

Open `index.php` and update the following constants with your values:

```php
const API_ID = 1234;              // Replace with your API ID
const API_HASH = '234nsldfklk';   // Replace with your API Hash
$target = -10023423;               // Replace with your target chat/channel ID
```

### Finding the Target Chat/Channel ID

The `$target` variable can be set in different ways:

1. **Numeric Channel/Group ID**: Use the numeric ID (usually negative for channels/groups)
   - Example: `$target = -1001234567890;`
   - You can find this using bots like [@userinfobot](https://t.me/userinfobot) or [@getidsbot](https://t.me/getidsbot)

2. **Username**: Use the username with @ prefix
   - Example: `$target = '@channelname';`

3. **Invite Link**: Use the full invite link
   - Example: `$target = 'https://t.me/joinchat/AAAAA...';`

## File Permissions

The script requires the following file permissions:

### Required Permissions

1. **Write access to the project directory** for:
   - `session.madeline` - Stores the Telegram session (created automatically)
   - `resume.json` - Stores download progress (created automatically)

2. **Write access to the download directory**:
   - The script creates a `download/` folder with permissions `0777` (as set in index.php line 42)
   - All media files are downloaded to this directory
   - Ensure the web server or PHP process user has write permissions
   - **Security Note**: The code uses `0777` which gives write access to all users. For better security, you may want to modify line 42 in `index.php` to use `0755` or `0775` instead, and ensure your PHP process has appropriate ownership

### Setting Permissions (Linux/Unix)

```bash
# Make the project directory writable
chmod 755 /path/to/telegram_batch_download

# If running as a web server, ensure proper ownership
# Replace www-data with your web server user if different
sudo chown -R www-data:www-data /path/to/telegram_batch_download

# Or if running from command line
chmod -R 755 /path/to/telegram_batch_download
```

## Usage

### Running the Script

From the command line:

```bash
php index.php
```

### First Run

On the first run, MadelineProto will prompt you to:

1. **Enter your phone number** (with country code, e.g., +1234567890)
2. **Enter the verification code** sent to your Telegram app
3. **Enter two-factor password** (if you have 2FA enabled)

After successful authentication, the session is saved in `session.madeline` for future use.

### What Happens During Download

The script will:

1. Connect to Telegram using your credentials
2. Fetch message history from the target chat/channel in batches (50 messages at a time)
3. Download all media files to the `download/` directory
4. Save progress to `resume.json` after each batch
5. Continue until all messages are processed

### Output

You'll see console output like:
```
Starting download for message ID 12345...
Downloaded verified file: document_12345.pdf
Starting download for message ID 12346...
Downloaded verified file: photo_12346.jpg
...
No more messages.
```

### Resume Capability

If the script is interrupted:
- The progress is saved in `resume.json`
- Simply run `php index.php` again to resume from where it left off
- To start fresh, delete `resume.json`

### Adjusting Download Settings

You can modify these settings in `index.php`:

```php
$limit = 50;  // Number of messages to fetch per batch (1-100)
sleep(3);     // Delay between batches in seconds (to avoid flood limits)
```

## Troubleshooting

### Common Issues

1. **"No such file or directory" for vendor/autoload.php**
   - Run `php composer.phar install` to install dependencies

2. **"Session expired" or "AUTH_KEY_DUPLICATED"**
   - Delete `session.madeline` and run the script again
   - You'll need to re-authenticate

3. **"FLOOD_WAIT" errors**
   - Telegram is rate-limiting your requests
   - Increase the `sleep(3)` delay in the main loop
   - Wait some time before trying again

4. **Permission denied when creating files**
   - Ensure the PHP process has write permissions to the directory
   - Check the "File Permissions" section above

5. **"Download failed" or size mismatch**
   - The script will automatically retry up to 3 times
   - Check your internet connection
   - Some files may be corrupted on Telegram's side

### Debug Mode

To see more detailed logs, increase the logger level in `index.php`:

```php
$settings = (new Settings)
    ->setAppInfo((new AppInfo)->setApiId(API_ID)->setApiHash(API_HASH))
    ->setLogger((new Logger)->setLevel(5));  // Change 1 to 5 for verbose logging
```

## Security Notes

- **Keep your API credentials secure**: Never commit `index.php` with real credentials to public repositories
- **Session files contain sensitive data**: The `session.madeline` file contains your authentication token
- **Consider environment variables**: For production use, consider using environment variables instead of hardcoding credentials

## License

This project uses MadelineProto, which is licensed under the AGPLv3 license.

## Credits

- Built with [MadelineProto](https://docs.madelineproto.xyz/) - A PHP implementation of the Telegram API
- Created for batch downloading media from Telegram channels and chats

## Disclaimer

This tool is for personal use only. Ensure you have the right to download content from the channels/groups you're accessing. Respect copyright and Telegram's Terms of Service.