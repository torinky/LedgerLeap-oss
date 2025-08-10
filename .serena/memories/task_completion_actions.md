
Before considering a task complete and creating a pull request, please follow these steps:

1.  **Run Tests:** Ensure all existing and new tests pass successfully.
    ```bash
    ./vendor/bin/sail pest
    ```

2.  **Format Code:** Apply the project's coding standards using Laravel Pint.
    ```bash
    ./vendor/bin/sail pint
    ```

3.  **Review Code:** Perform a self-review of your changes to catch any potential issues.

4.  **Update Documentation:** If your changes affect the system's architecture, functionality, or require new setup steps, update the relevant documents in the `/docs` directory.

5.  **Create Pull Request:** Push your feature branch and create a pull request against the `develop` branch.

6.  **Write a Clear Commit Message:** Follow the Conventional Commits format in Japanese. The message should clearly explain the 'what' and 'why' of the changes.
    -   **Example:** `feat(ledger): 新しい台帳項目の追加機能`
