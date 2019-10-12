# Markdown

![license](https://img.shields.io/badge/license-MIT-brightGreen.svg)
[![build](https://travis-ci.org/originphp/markdown.svg?branch=master)](https://travis-ci.org/originphp/markdown)
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