EU Windhound Race Suite - Installation

1) Create MySQL database on InfinityFree.
2) Import /eu_race/install.sql in phpMyAdmin.
3) Upload /eu_race folder to hosting root.
4) Edit /eu_race/inc/config.php with DB credentials.
5) Open /eu_race/login.php and login:
   user: admin
   pass: Admin123!
6) Change the password immediately by updating users table with a new bcrypt hash.

PDF:
- Upload vendor/dompdf (composer vendor folder) to /eu_race/vendor.
- If Dompdf is missing, system falls back to HTML output.
