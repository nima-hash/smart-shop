# Symfony 7 E-commerce Backend Showcase

This repository contains the backend code for a functional e-commerce platform built with **Symfony 7**. This project was my first major dive into the framework, with a strong focus on mastering backend logic, architecture, and database management. The code within this repository is a showcase of my coding skills, specifically demonstrating the application's core business logic and controller layer, and is not intended as a production-ready, full-stack application.

## Core Functionalities

* **User Management:** A robust system for user registration and authentication, ensuring secure access to the platform.
* **Product Catalog:** A dynamic product catalog that includes a functional shopping cart and leads to a secure checkout process.
* **Database Management:** The application’s core entities, such as `User` and `Product`, are meticulously designed and managed through **Doctrine ORM** and a **MySQL** database.
* **Deployment:** The project provided practical exposure to the full application lifecycle, including a manual deployment process.

## Technical Stack & Packages

This project leverages Symfony's native components alongside a curated list of third-party packages to extend its capabilities.

* **Framework Core:**
    * **Symfony 7:** The primary PHP framework providing the foundational structure.
    * **Doctrine ORM:** Used for all database interactions.
    * **Twig:** The templating engine for all front-end views.
* **External Dependencies:**
    * `league/oauth2-client`: For handling modern authentication methods.
    * `guzzlehttp/guzzle`: An HTTP client for managing external API calls.
    * `egulias/email-validator`: A comprehensive validator for email addresses.
    * `slugify`: To create clean, SEO-friendly URLs.
    * `knplabs/knp-components`: Used for handling pagination and other common components.
    * `monolog/monolog`: For application logging and error handling.

## Status & Future Plans

The project is still under active development. The current focus is entirely on the backend architecture, and as such, the frontend design is kept straightforward. Future plans include:

* **Frontend Enhancement:** A complete redesign of the user interface and overall user experience.
* **New Features:** The addition of a blog, an advanced order management system with returns and refunds, and a robust payment processing system.
