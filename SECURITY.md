# Security Policy

Thank you for taking the time to help secure **Pomposer**.

This document outlines how to report security vulnerabilities and what to expect from the process.

---

## Supported Versions

| Version       | Supported         |
|---------------|-------------------|
| `dev-main`    | ✅ Yes (active)    |
| `pre-releases`| ⚠️ Best effort     |
| `stable tags` | ❌ Not yet released |

> Pomposer is still in early development (beta), so all security efforts are focused on the current `main` branch.

---

## Reporting a Vulnerability

If you discover a security issue in Pomposer:

- **Do not open a public issue.**
- Instead, please contact the maintainer directly at:

📧 [hichem.taboukouyout@hichemtab-tech.me](mailto:hichem.tab@hichemtab-tech.me)

Please include:
- A detailed description of the issue
- Steps to reproduce (if possible)
- Potential impact

---

## Security Practices

Pomposer is **not yet production-ready** and is undergoing rapid development. However, we do aim to:

- Avoid unsafe file access or eval-like behavior
- Sanitize inputs used in command execution
- Use proven libraries (e.g., Symfony, Laravel components) for sensitive operations

---

## Responsible Disclosure

We appreciate responsible disclosure. All legitimate vulnerability reports will be reviewed and responded to promptly.

---

Thank you for helping keep Pomposer safe 🙏
