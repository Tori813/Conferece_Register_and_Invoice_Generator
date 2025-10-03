# Conference Registration and Invoice Generator

A web application for managing conference registrations and generating invoices with email notifications.

## Features

- Multi-attendee registration
- PDF invoice generation
- Email notifications with PDF attachments
- Configurable SMTP settings
- Environment-based configuration
- Secure file uploads
- Responsive design

## Requirements

- PHP 7.4 or higher
- Composer
- Web server (Apache/Nginx)
- SMTP server (e.g., Gmail, SendGrid, etc.)

## Installation

1. Clone the repository:
   ```bash
   git clone https://github.com/yourusername/conference-registration.git
   cd conference-registration
   ```

2. Install dependencies:
   ```bash
   composer install --no-dev --optimize-autoloader
   ```

3. Configure the application:
   - Copy `config.php` to `config.local.php` and update the settings:
     ```php
     <?php
     return [
         'smtp' => [
             'host' => 'smtp.gmail.com',
             'port' => 587,
             'username' => 'vaokello@gmail.com',
             'password' => 'odizryolxybtsgzf',
             'from_email' => 'vaokello@gmail.com',
             'from_name' => 'Conference Registration',
             'secure' => 'tls',
             'debug' => 0
         ],
         'app' => [
             'env' => 'production',
             'debug' => false,
             'timezone' => 'Africa/Nairobi',
             'max_upload_size' => 5 * 1024 * 1024 // 5MB
         ],
         'cc_recipients' => [
             ['email' => 'vokello@mespt.org', 'name' => 'Victoria Okello']
         ]
     ];
     ```

## Usage

1. Access the application through your web browser:
   ```
   http://localhost/Conferece_Register_and_Invoice_Generator/MultipleRegistration.html
   ```

2. Fill in the registration form and submit.
3. The system will generate a PDF invoice and send it to the provided email address.

## Security

- All sensitive data is stored in the configuration file outside the web root.
- File uploads are validated for type and size.
- CSRF protection is implemented in the forms.
- Secure headers are set in the .htaccess file.

## Development

1. Install development dependencies:
   ```bash
   composer install
   ```

2. Run PHP built-in server:
   ```bash
   php -S localhost:8000 -t .
   ```

3. Run tests:
   ```bash
   composer test
   ```

4. Check code style:
   ```bash
   composer check-style
   ```

5. Fix code style issues:
   ```bash
   composer fix-style
   ```

## License

MIT

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request
