@page tags Latte tags @brief Available latte tags @details All custom latte tags created and available in latte
templates.

## link

Generate a link url.

```latte
{link ['page', 'param1', 'param2', 'name' => 'value']} -> 'http(s)://domain.cz/page/param1/param2?name=value
```

## getUrl

Get site's root URL.

```latte
{getUrl} -> http(s)://domain.cz/
```

## csrf

Get generated CSRF token.

```latte
{csrf name} -> string: token
```

## csrfInput

Get a hidden input with generated CSRF token.

```latte
{csrfInput name} -> <input type="hidden" name="_csrf_token" value="token"/>
```

## alert

Generate an HTML alert message

```latte
{alert msg, type} ->
<div class="alert alert-type">msg</div> 
```

## alertDanger

Generate an HTML error alert message

```latte
{alertDanger msg} ->
<div class="alert alert-danger">msg</div> 
```

## alertSuccess

Generate an HTML success alert message

```latte
{alertSuccess msg} ->
<div class="alert alert-success">msg</div> 
```

## alertWarning

Generate an HTML warning alert message

```latte
{alertWarning msg} ->
<div class="alert alert-warning">msg</div> 
```
