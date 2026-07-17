This pull request includes the changes for upgrading to Laravel 12.x. Feel free to commit any additional changes to the `main` branch.

**Before merging**, you need to:

- Checkout the `main` branch
- Review **all** pull request comments for additional changes
- Run `composer update` (if the scripts fail, try with `--no-scripts`)
- Clear any config, route, or view cache
- Thoroughly test your application{{test_upsell}}

If you get stuck, never hesitate to [email support](mailto:support@laravelshift.com). If you need more help with your upgrade, check out the [Human Shifts](https://laravelshift.com/human-shifts).


1. :information_source: Laravel 12 removed the `MAIL_ENCRYPTION` environment variable. While a new `MAIL_SCHEME` environment variable was introduced, its available options are not the same. Your application will likely behave correctly with the new default values. However, if you were setting `MAIL_ENCRYPTION` to `tls`, you may want to review this [Mastering Laravel Tip](https://masteringlaravel.io/daily/2025-06-18-what-value-should-i-use-for-mail-scheme) for more details.

2. :warning: Laravel 12 changed the default values of the `CACHE_PREFIX`, `REDIS_PREFIX`, and `SESSION_COOKIE` to use dashes (`-`) instead of underscores (`_`). If you were not explicitly setting these environment variables, your application will use these new default values - which may result in unexpected behavior. For example, temporary cache misses or forced log out.

If your application uses cache prefixes or session cookies and you were not explicitly setting these ENVs, you may set them to their previous defaults to avoid any unexpected behavior.

<details>
<summary><b>&nbsp;✨ Automate more with AI...</b></summary><p>

Shift automates the changes it determines are reliable. If you want to push this automation further, you may paste the following prompt into your AI of choice:

```md
I'm upgrading to Laravel 12. The default values of `CACHE_PREFIX`,
`REDIS_PREFIX`, and `SESSION_COOKIE` changed to use dashes instead of
underscores. Please check my config files and `.env` to determine if these are
explicitly set or relying on the old defaults. If relying on defaults or
missing, suggest the explicit values I should add to my `.env` to preserve the
previous behavior.


```
</p></details>

3. :warning: Laravel 12 removed the `APP_TIMEZONE` environment variable. The timezone now defaults to `UTC`. Shift [removed this variable](a480a6013f06d6d6eb3391a1773392c01e09a3dc) from your `.env` file since it was set to `UTC`.

Shift detected additional references to the `APP_TIMEZONE` environment variable in the following files. You should remove the environment variable and, if necessary, reference the `app.timezone` configuration option instead.

- [ ] config/app.php
- [ ] vendor/laravel/framework/config-stubs/app.php
- [ ] vendor/laravel/framework/config/app.php

4. :information_source: Laravel added a `composer run setup` script which runs commands like  `composer install`, `npm install`, and others to _set up_ your Laravel application. For convenience, Shift added the default script. You are encouraged to customize this script for your project.

5. :information_source: Laravel added a `composer run test` script which runs the new `php artisan config:clear` command, then `php artisan test`. For convenience, Shift added the default script. You are welcome to customize this script for your test suite.

6. :information_source: Shift [updated your dependencies](7096da0a531a1d48ae36cb91bac178de5af5f97d) for Laravel 12. While many of the popular packages are reviewed, you may have to update additional packages in order for your application to be compatible with Laravel 12. Watch [dealing with dependencies](https://laravelshift.com/videos/update-incompatible-composer-dependencies) for tips on handling any Composer issues.

7. :information_source: If you are using `laravel new` to create new Laravel applications, you should update to the latest version to receive updates for Laravel 12 and the new starter kits.

You may update the Laravel installer by running:

```sh
composer global require laravel/installer
```

8. :warning: Laravel 12 no longer includes the SVG image type when performing image validation. If your application allows SVG images, you should review your image validation rules and update them to use the new `allow_svg` option.

```php
'cover' => ['required', 'image:allow_svg'],
'avatar' => ['required', File::image(allowSvg: true)],
```

<details>
<summary><b>&nbsp;✨ Automate more with AI...</b></summary><p>

Shift automates the changes it determines are reliable. If you want to push this automation further, you may paste the following prompt into your AI of choice:

```md
I'm upgrading to Laravel 12. The `image` validation rule no longer allows SVG
files by default. Please find everywhere I use the `image` validation rule or
`File::image()` and report back the locations so I can decide which ones need
the new `allow_svg` option.


```
</p></details>

9. :information_source: The container now respects the default value of constructor parameters when resolving a class instance. If you were previously relying on the container to set a value, you will need to pass in this value when resolving the class instead.

<details>
<summary><b>&nbsp;✨ Automate more with AI...</b></summary><p>

Shift automates the changes it determines are reliable. If you want to push this automation further, you may paste the following prompt into your AI of choice:

```md
I'm upgrading to Laravel 12. The container now respects the default value of
constructor parameters when resolving class instances. Please review the
following files for any `app()`, `make()`, or `resolve()` calls where the
resolved class has constructor parameters with default values that my code was
previously relying on the container to override.

- database/factories/UserFactory.php
```
</p></details>