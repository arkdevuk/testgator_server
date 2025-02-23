# TestGator

![GatorAvatar](https://github.com/arkdevuk/testgator_client/blob/main/public/assets/display.jpg?raw=true)

TestGator is a streamlined testing platform that helps development teams efficiently distribute and collect feedback on
testing plans from end-users. Designed to simplify the workflow, TestGator enables teams to send interactive test plans
to non-technical users, gather structured feedback, and collect user environment data automatically.

## Features

- **Interactive Testing Plans:** Dev teams can create detailed testing plans with structured questions.
- **Signed URL Access:** Each tester receives a unique, secure URL to access their assigned test plan.
- **Simple Feedback System:** Testers provide feedback using `PASS | PASS (with bug) | FAILED` along with optional
  comments and attachments.
- **File & Screenshot Uploads:** Testers can submit images, logs, and other relevant files.
- **Automatic Environment Collection:** Browser, OS, screen resolution, and geolocation are captured automatically.
- **Project & Release Management:** Support for multiple projects, each with its own releases and test plans.
- **Role-based Testing Assignments:** Assign different testers for each plan or re-use existing ones.
- **API & GUI Integration:** Separate repositories for the backend API (`testgator_server`) and frontend client (
  `testgator_client`).

## Repository Structure

### TestGator Client (`testgator_client`)

- A modern web-based GUI for testers and developers.
- Developed using modern frontend technologies.
- Provides an intuitive interface for test execution and feedback submission.

### TestGator Server (`testgator_server`)

- REST API backend for managing projects, releases, testing plans, and user feedback.
- Handles authentication, signed URL generation, and data storage.
- Manages tester assignments and environment tracking.

## Getting Started

### Prerequisites

Ensure you have the following installed on your system:

- PostgreSQL
- Docker

### Installation

#### Backend (API)

WIP

#### Frontend (GUI)

WIP

## Usage

1. **Developers** create a project and define releases.
2. **Test plans** are created within each release and assigned to testers.
3. **Testers receive signed URLs** via email and access their assigned plans.
4. **Testers submit feedback** by selecting a test result, adding comments, and uploading screenshots/files.
5. **Collected data** is available in the TestGator dashboard for review.
6. **Devs analyze feedback**, track issues, and iterate on releases accordingly.

## API Documentation

API documentation is available at:
WIP

## Contribution Guidelines

Contributions are welcome! Follow these steps to contribute:

1. Fork the repository.
2. Create a new branch for your feature/fix.
3. Commit your changes and push them.
4. Open a Pull Request for review.

## License

TestGator is licensed under the MIT License. See [LICENSE](LICENSE) for details.

## Contact

For any questions or issues, feel free to open an issue on GitHub on the respective repository.

---
TestGator – Simplifying End-User Testing for Dev Teams 🚀
---

## Why worker mode is not enabled on TestGator ?

Because of LDAP ext we can't run the worker mode. Because the connection to the LDAP server will fail at some point.

`see : https://github.com/dunglas/frankenphp/issues/457`
