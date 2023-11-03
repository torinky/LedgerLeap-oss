# LedgerLeap

Ledger and Document Management System

---

## License

The Laravel framework is open-sourced software licensed under the [MIT license](https://opensource.org/licenses/MIT).

## Note

### sailコマンドを使えるようにする

次を参考にする
https://readouble.com/laravel/9.x/ja/sail.html#installing-composer-dependencies-for-existing-projects

次のコマンドでphpのdockerが起動してcomposer installでphpの依存関係が解決される

docker run --rm \
-u "$(id -u):$(id -g)" \
-v $(pwd):/var/www/html \
-w /var/www/html \
laravelsail/php81-composer:latest \
composer install --ignore-platform-reqs

## Credit

Japanese Wordnet (v1.1) © 2009-2011 NICT, 2012-2015 Francis Bond and 2016-2022 Francis Bond, Takayuki Kuribayashi\
https://bond-lab.github.io/wnja/index.en.html

日本語ワードネット （1.1版）© 2009-2011 NICT, 2012-2015 Francis Bond and 2016-2022 Francis Bond, Takayuki Kuribayashi\
https://bond-lab.github.io/wnja/index.ja.html
