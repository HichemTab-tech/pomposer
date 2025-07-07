# 🤝 Contributing to Pomposer

Thank you for your interest in contributing to **Pomposer** — a smarter, experimental wrapper for Composer that brings global caching and dependency deduplication to PHP.

Pomposer is in **very early beta**, and your ideas, feedback, and code are essential to shaping its future. This guide will help you get started.

---

## 🚀 What You Can Contribute

We welcome contributions of all kinds:

- 💡 **Ideas**: Propose features or improvements [in Discussions](https://github.com/HichemTab-tech/pomposer/discussions/4)
- 🐞 **Bug reports**: Find something broken? Open an issue.
- 🔨 **Code**: Help us write clean, tested PHP.
- 🧪 **Tests**: Unit, integration, and edge-case testing.
- 📚 **Docs**: Help with writing clear docs and examples.
- 📈 **Feedback**: Try it out and let us know what works or doesn’t.

---

## 🛠 Local Setup

### 1. **Clone the repo**

```bash
git clone https://github.com/HichemTab-tech/pomposer.git
cd pomposer
```

Let’s say you cloned it to:

```
~/Projects/pomposer
```

### 2. Go to your global Composer folder

You can check where it is with:

```bash
composer global config home
```

Usually something like:

```
~/.config/composer
```

### 3. Modify `composer.json` in that directory:

Add this under `require` and `repositories`:

```json
{
  "require": {
    "hichemtab-tech/pomposer": "@dev"
  },
  "repositories": [
    {
      "type": "path",
      "url": "/absolute/path/to/cloned/pomposer"
    }
  ]
}
```

> Replace `/absolute/path/to/cloned/pomposer` with the real full path to the cloned repo.
> On Windows, escape backslashes (`\\`) or use forward slashes.

---

#### 4. Run install

```bash
composer global update
```

You can create test projects with their own `composer.json` to simulate usage.

---

## Code Structure (WIP)

* `src/Console`: CLI commands like `InstallCommand`
* `src/`: Core classes (e.g. `PackageInstaller`, `PackageStore`)
* `~/.pomposer-store/`: Global storage (packages by vendor/name/version)

---

## Testing (Coming Soon)

We’re working on adding Pest or PHPUnit. For now, please test manually in local projects.

---

## Coding Guidelines

* Use modern PHP (>= PHP 8.2)
* Follow PSR-12 formatting
* Write **clear, documented** code
* Avoid over-engineering — keep it practical and hackable
* Use Symfony or Laravel helpers when appropriate (Filesystem, Console, etc.)

---

## Submitting a Pull Request

1. Fork the repo
2. Create a new branch
3. Make your changes
4. Add tests (if applicable)
5. Commit with a meaningful message
6. Open a PR with a clear description

---

## 🛑 Before You Start Big Changes

If you plan to refactor something significant or add a big feature, **please open a discussion or issue first** so we can align before you spend time coding.

---

## ❤️ Thank You

Pomposer is a fun experiment — and maybe the beginning of a better dependency management workflow for PHP. Thanks for being part of that journey!

— HichemTab-tech