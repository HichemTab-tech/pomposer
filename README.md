# Pomposer - A Smarter Composer Wrapper (pnpm-style for PHP)

A blazing-fast, cache-aware Composer wrapper that shares packages globally between PHP projects — inspired by `pnpm`.


## Why Pomposer?

Composer is the backbone of modern PHP development , but it's not optimized for shared storage. Every `composer install` duplicates packages per project, eating up disk space and time.

**Pomposer** brings the best of `pnpm` to PHP:

- 📦 **Global package store**: Each package version is installed once, then reused everywhere
- 🔗 **Vendor symlinking/copying**: Creates local `vendor/` folders using lightweight links
- ⚡ **Faster installs**: Once packages are cached, installs are nearly instant
- 🧠 **Lock-free support**: Works with or without `composer.lock`
- 🪄 **Custom autoload generator**: Supports PSR-4 and classmap autoloading

---

## How It Works

1. **Read `composer.lock`** (or fallback to `composer.json` with **recursive** dependency resolution)
2. **Download packages individually** (via Packagist ZIPs - no `composer install` required)
3. **Store each package by version** in `~/.pomposer-store`
4. **Link/copy packages** into `vendor/` structure
5. **Generate autoload files** similar to Composer (PSR-4 + classmap)

---

## Installation

You can install Pomposer globally or locally via Composer:

```bash
composer global require hichemtab-tech/pomposer
```

Make sure Composer global bin is in your `$PATH`. Then run:

```bash
pomposer install
```

---

## Example: Build a Real Project with Pomposer

Let’s test Pomposer using a simple PHP app that:

* Uses `monolog/monolog` for logging
* Uses your custom package `hichemtab-tech/namecrement`
* Has its own PSR-4 autoloading

---

### 1. Create your test project

```bash
mkdir test-pomposer && cd test-pomposer
```

### 2. Create a `composer.json`:

```json
{
  "name": "hichemtab-tech/test-pomposer",
  "autoload": {
    "psr-4": {
      "HichemTabTech\\TestPomposer\\": "src/"
    }
  },
  "authors": [
    {
      "name": "HichemTab-tech",
      "email": "konanhichemsinshi@gmail.com"
    }
  ],
  "require": {
    "hichemtab-tech/namecrement": "^1.1",
    "monolog/monolog": "^3.9"
  }
}
```

### 3. Create your source and test files

```bash
mkdir src
touch src/index.php
```

Then edit `src/index.php`:

```php
<?php

require_once __DIR__ . '/../vendor/autoload.php';

use HichemTabTech\Namecrement\Namecrement;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$existing = ['file', 'file (1)', 'file (2)'];
$newName = Namecrement::namecrement('file', $existing);

echo "Next unique file name after the list of existing files:\n";
echo "Existing files: " . implode(', ', $existing) . "\n";
echo "New file name: ";
echo $newName . "\n\n";

$log = new Logger('test');
$log->pushHandler(new StreamHandler('php://stdout', Logger::DEBUG));
$log->info('Pomposer is alive!');
```

### 4. Run Pomposer

```bash
pomposer install
```

It will:

* Resolve dependencies (even if no `composer.lock`)
* Download and cache each package version in `~/.pomposer-store`
* Link the needed packages into your `vendor/` folder
* Generate a working autoloader

### 5. Run the test script

```bash
php src/index.php
```

✅ You should see output like:

```
Next unique file name after the list of existing files:
Existing files: file, file (1), file (2)
New file name: file (3)

[2025-07-06 20:31:22] test.INFO: Pomposer is alive! []
```

✅ You just installed `monolog/monolog` without Composer touching your `vendor/` at all.

---

## Global Store Layout

Packages are stored by name + version:

```
└── ~/.pomposer-store/
    ├── hichemtab-tech
    │   └── namecrement
    │       └── 1.1.0
    │           ├── composer.json
    │           └── src
    ├── monolog
    │   └── monolog
    │       └── 3.9.0
    │           ├── composer.json
    │           └── src
    └── psr
        └── log
            └── 3.0.2
                ├── composer.json
                └── src
```

---

## ⚠️ Limitations (Beta Notice)

> [!WARNING]  
> Pomposer is still in **beta** — built as a proof of concept.

Current limitations:

* ❌ No plugin support (e.g., Laravel installer, Symfony flex)
* ❌ No script execution (`post-install-cmd`, etc.)
* ❌ No support for `provide`, `replace`, or `conflict`
* ⚠️ Only supports packages with valid `dist.zip` from Packagist
* ✅ Supports PSR-4 and classmap, not `files` or `psr-0` (yet)

---

## 💡 Want to Contribute?

Got ideas or experience with Composer internals? Want to help evolve Pomposer into something production-ready?

👉 **Join the discussion and contribute on GitHub**:
[https://github.com/HichemTab-tech/pomposer](https://github.com/HichemTab-tech/pomposer)

---

## License

MIT © @HichemTab-tech
