<<<<<<< HEAD
# TriHealth Mart - Modern Health & Wellness E-commerce Platform

A modern, responsive e-commerce website built with PHP, MySQL, and Bootstrap 5, focusing on health and wellness products.

## Features

- Responsive design that works on all devices
- User authentication system
- Product catalog with categories
- Shopping cart functionality
- Order management
- User reviews and ratings
- Admin dashboard
- Secure payment processing
- Modern UI with smooth animations

## Requirements

- PHP 7.4 or higher
- MySQL 5.7 or higher
- XAMPP (or similar local development environment)
- Web browser with JavaScript enabled

## Installation

1. Clone the repository to your XAMPP htdocs directory:
   ```bash
   git clone https://github.com/yourusername/trihealth-mart.git
   ```

2. Start XAMPP and ensure Apache and MySQL services are running

3. Create a new database in phpMyAdmin:
   - Open phpMyAdmin (http://localhost/phpmyadmin)
   - Create a new database named `trihealth_mart`
   - Import the database schema from `database/schema.sql`

4. Configure the database connection:
   - Open `config/database.php`
   - Update the database credentials if needed:
     ```php
     define('DB_HOST', 'localhost');
     define('DB_USER', 'root');
     define('DB_PASS', '');
     define('DB_NAME', 'trihealth_mart');
     ```

5. Access the website:
   - Open your browser and navigate to `http://localhost/trihealth-mart`

## Directory Structure

```
trihealth-mart/
├── assets/
│   ├── css/
│   ├── js/
│   └── images/
├── config/
│   └── database.php
├── database/
│   └── schema.sql
├── includes/
│   ├── header.php
│   └── footer.php
├── admin/
│   ├── dashboard.php
│   ├── products.php
│   └── orders.php
├── index.php
├── login.php
├── register.php
└── README.md
```

## Features to Implement

- [ ] Product search functionality
- [ ] Advanced filtering options
- [ ] Wishlist feature
- [ ] Email notifications
- [ ] Social media integration
- [ ] Multi-language support
- [ ] Advanced analytics
- [ ] Mobile app integration

## Contributing

1. Fork the repository
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

This project is licensed under the MIT License - see the LICENSE file for details.

## Support

For support, email support@trihealthmart.com or create an issue in the repository. 
=======
# trihealth-mart
>>>>>>> d7786f3ced78d3bd8ea8171c8b61c3d2e6905a54
