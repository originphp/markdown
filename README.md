# Markdown

![license](https://img.shields.io/badge/license-MIT-brightGreen.svg)
[![build](https://github.com/originphp/markdown/workflows/CI/badge.svg)](https://github.com/originphp/markdown/actions)
[![coverage](https://coveralls.io/repos/github/originphp/markdown/badge.svg?branch=master)](https://coveralls.io/github/originphp/markdown?branch=master)

The Markdown utility gives you useful functions for converting to and from markdown.

## Installation

To install this package

```linux
$ composer require originphp/markdown
```

## Convert markdown To HTML

To convert HTML to markdown

```php
$html = Markdown::toHtml($markdown);
```

By default markdown is escaped before converting to HTML this prevents people putting malicious HTML code. You can disable this by passing the options array with `escape` set to false.

```php
Markdown::toHtml($markdown,['escape'=>false]);
```


## Convert markdown To Text

To convert markdown to text:

```php
$text = Markdown::toText($markdown);
```

## Convert to markdown From HTML

To convert HTML to markdown.

```php
$markdown = Markdown::fromHtml($html);
```