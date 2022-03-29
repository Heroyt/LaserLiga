@page pages-page Creating pages @brief Documentation about creating page classes

All page classes need to be in a `Pages` namespace and inherit from `Core\Controller` abstract class.

## Properties

All page classes have 3 main properties.

These properties define the main information about the page itself and should be set for each page.

#### Title (gets translated)

```php
protected string $title = '';
```

#### Description (gets translated)

```php
protected string $description = '';
```

#### Template file name

Only the name without the `.latte` suffix

```php
protected string $templateFile = '';
```

#### Latte parameters

Set in `init()` method.

```php
protected array $params = [];
```

## Methods

All pages have 4 main methods that can be called. These methods are inherited from the `Core\Controller` class, but can
be modified.

#### Init

default:

```php
public function init() : void {}
```

#### getTitle

default:

```php
public function getTitle() : string {
   return Constants::SITE_NAME.(!empty($this->title) ? ' - '.lang($this->title) : '');
}
```

#### getDescription

default:

```php
public function getDescription() : string {
  return lang($this->description);
}
   ```

#### generate

default:

```php
public function generate(bool $return = false) : ?string {
	$this->params['page'] = $this;
	if ($return) {
	  return App::$latte->renderToString(getTemplate($this->templateFile), $this->params);
	}
	App::$latte->render(getTemplate($this->templateFile), $this->params);
	return null;
}
```

## Login check

Pages can be set to login-only. To create a login-only page, it needs to implement: `Core\NeedsLoginInterface` and use
a `Core\NeedsLogin` trait.

### Setting requirements

Page can also have a set of requirements for user. These requirements are set within protected properties.

#### Types

User types that are allowed to view this page.

```php
protected array $allowedTypes = [DB::ADMIN_TYPE, DB::USER_TYPE];
```

#### Rights

Minimal rights for admin users to view this page.

```php
protected int $minRights = 0;
```

### Example:

This creates a new `With login` page that needs a user to be logged in with type of `ADMIN` and minimal rights of `5`.

```php
use \Core\Controller;
use \Core\NeedsLoginInterface;
use \Core\NeedsLogin;
use \Core\DB;

namespace Pages;

class WithLoginPage extends Page implements NeedsLoginInterface
{
	use NeedsLogin;

	protected string $title = 'With login';
	protected string $description = 'Page that needs user to be logged in.';
	protected string $templateFile = 'withLogin';

protected array $allowedTypes = [DB::ADMIN_TYPE];
protected int $minRights = 5;

}
```
