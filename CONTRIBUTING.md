# Contributing to SCM ERP

First off, thank you for considering contributing to the SCM ERP project! It's people like you that make the open-source community such a powerful place to learn, inspire, and create.

## Branching Strategy

- **`main`**: The stable branch. This branch is protected. **Never push directly to `main`.**
- **`develop`**: The integration branch for new features and bug fixes. All pull requests should be made against this branch.

## How to Contribute

1. **Fork the Repository**: Click the 'Fork' button at the top right of this page.
2. **Clone your Fork**:
   ```bash
   git clone https://github.com/YOUR_USERNAME/Supply-Chain-Management.git
   cd Supply-Chain-Management
   ```
3. **Add the Upstream Remote**:
   ```bash
   git remote add upstream https://github.com/mesourish/Supply-Chain-Management.git
   ```
4. **Checkout the Develop Branch**:
   ```bash
   git checkout develop
   ```
5. **Create a Feature Branch**:
   ```bash
   git checkout -b feature/your-feature-name
   ```
6. **Make your Changes**: Write your code, update tests, and ensure everything runs smoothly.
7. **Commit your Changes**: Use clear, descriptive commit messages.
   ```bash
   git commit -m "feat: add user authentication"
   ```
8. **Push to your Fork**:
   ```bash
   git push origin feature/your-feature-name
   ```
9. **Submit a Pull Request (PR)**: Go to the original repository on GitHub and click "Compare & pull request". Make sure to set the base branch to `develop`.

## Code Guidelines
- Follow standard Laravel coding conventions (PSR-12).
- Write clear and meaningful comments where logic is complex.
- Ensure all existing tests pass before submitting your PR.
- If you're adding a new feature, please include basic tests (PHPUnit/Pest) to verify it works.

Thanks again for your contribution!
