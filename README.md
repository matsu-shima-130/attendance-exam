# 勤怠管理アプリ

## 環境構築

### Docker ビルド

1.  リポジトリを取得

    ```bash
    git clone git@github.com:matsu-shima-130/attendance-exam.git
    cd attendance-exam
    ```

2.  コンテナを作成・起動

    ```bash
    docker-compose up -d --build
    ```

    ＊MYSQL は、OS によって起動しない場合があるのでそれぞれの PC に合わせて docker-compose.yml ファイルを編集してください。

### Laravel 環境構築

1. PHP コンテナに入る

   ```bash
   docker-compose exec php bash
   ```

2. 依存をインストール & 環境ファイル

   ```bash
   composer install
   cp .env.example .env
   php artisan key:generate
   ```

3. .env の DB 設定

   ```bash
   DB_CONNECTION=mysql
   DB_HOST=mysql
   DB_PORT=3306
   DB_DATABASE=laravel_db
   DB_USERNAME=laravel_user
   DB_PASSWORD=laravel_pass
   ```

4. メール認証（MailHog）設定（.env）

   ```bash
   MAIL_MAILER=smtp
   MAIL_HOST=mailhog
   MAIL_PORT=1025
   MAIL_FROM_ADDRESS=no-reply@example.com
   MAIL_FROM_NAME="Attendance App"
   ```

5. Fortify（初回のみ）

   ```bash
   php artisan vendor:publish --provider="Laravel\\Fortify\\FortifyServiceProvider"
   ```

6. マイグレーション

   ```bash
   php artisan migrate
   ```

7. シーディング

   ```bash
   php artisan db:seed
   ```

## 使用技術

- PHP 8.1.33
- Laravel 8.83.8
- MySQL 8.0.26
- Nginx 1.21.1

## 認証

- Laravel Fortify

## URL

- 開発環境: http://localhost/
- phpMyAdmin: http://localhost:8080/

## 管理者アカウント（Seeder で作成）

- email: admin@example.com
- password: password123

## ER 図

![ER図](docs/er.png)
