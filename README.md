# Khata — Personal Hisab Kitab

Aik simple web tool (PHP + MySQLi + Bootstrap 5) apne paison ka hisab rakhne k liye:

| Module | Kya karta hai |
|---|---|
| **Login + OTP** | Username/password k baad email par 6-digit OTP (5 min valid, 5 ghalat koshishein, 60s baad resend). 15 min mein 5 ghalat logins pr IP block. |
| **Settings** | SMTP (host, port, TLS/SSL, user, encrypted password, from) + test email, OTP on/off, password change, opening cash, expense categories. |
| **Salary / Income** | Har maheenay ki salary, bonus waghera. Cash mein jama hoti hai. |
| **Expenses** | Tareekh + category k sath kharcha, maheena wise filter, category breakdown, 6 maheenay ka trend. Cash se minus. |
| **Cash Book** | Opening cash + saari entries ka running balance. "Cash ginti se milayein" se farq adjust. |
| **Investments** | Har investment mein jab jab installment do, add karte jao (pehle se fix nahi). Profit aur asal raqam ki wapsi alag. ROI, mahana average profit, saal wise. |
| **Udhar** | Har bande ka account: maine diya / usne wapas kiya / maine liya / maine wapas kiya. Kaun kitna dega / mujhe kitna dena hai. |
| **Reports** | Saal ka maheena-wise hisab: income, kharcha, bachat, investment, har maheenay k aakhir ka cash; category x maheena; rent banta tha vs wusool hua. Print bhi ho sakta hai. |
| **Backup** | Settings → Backup: poore database ki `.sql` file aur documents ki `.zip` download. phpMyAdmin → Import se wapas. |
| **Rent** (alag hisab, cash se koi taluq nahi) | Makan → kirayedar (naam, phone, CNIC, advance, stamp paper ki pictures/PDF). Maheena wise kiraya banta hai, payment FIFO se purane maheenon mein adjust hoti hai — gap/aadha maheena khud nazar aata hai. Increment reminder (har X maheenay, % ya fixed), jis maheenay se asal mein barha wahi likhein. Kirayedar chhor jaye to final hisab: baqi kiraya advance se kaato, nuqsan ki kaat, advance wapsi. WhatsApp yaad dehani. Har payment ki printable raseed (kis maheenay ka kitna, aur baqaya). Makan ka kharcha (repair, tax). |

## Install (XAMPP / WAMP / cPanel)

1. Saara folder `htdocs/khata` (ya hosting ki `public_html/khata`) mein copy karein.
2. phpMyAdmin mein aik database banayein, e.g. `khata` (utf8mb4).
3. `config.php` mein `DB_HOST, DB_USER, DB_PASS, DB_NAME` likhein aur `APP_KEY` ko aik lambi random string se badal dein.
   (Chahein to ye settings `config.local.php` mein rakhein — wo git mein nahi jati.)
4. Browser mein `http://localhost/khata/` kholein → **setup** page khud aayega: admin account + opening cash banayein. Tables khud ban jayengi (ya `database.sql` import kar dein).
5. Login karein → **Settings → Email (SMTP)** set karein → *Test email* bhejein. Is k baad har login par OTP aayega.

> SMTP set hone tak OTP skip hota hai (warning k sath), taake aap pehli dafa lock na ho jayein.

### Gmail SMTP
2-Step Verification on karein → App Password banayein → Host `smtp.gmail.com`, Port `587`, TLS, username = Gmail, password = App Password.

## Requirements
PHP 8.0+ (mysqli, openssl, fileinfo), MySQL 5.7+ / MariaDB 10.3+. Bootstrap aur icons `assets/vendor` mein shamil hain — internet ki zaroorat nahi.

## Security notes
- Passwords `password_hash`, OTP bhi hash ho kar save hota hai; saare forms par CSRF token; sab queries prepared statements.
- `uploads/` aur `includes/` direct access band (`.htaccess`, Apache). Documents sirf login k baad `file.php` se khulte hain. Nginx par in folders ko khud deny karein.
- Live server par HTTPS zaroor use karein.
