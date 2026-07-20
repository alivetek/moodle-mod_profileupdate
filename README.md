# moodle-mod_profileupdate

Provide a "Profile Update" activity for Moodle allowing users to update user profile fields from within a course, i.e., without the need to link out to the user profile page.  This can be useful when requiring custom user profile fields while at the same time, serving courses as LTI tools for consumption in another LMS.  Allowing profile updates directly within a course activity avoids users having to navigate to a profile page, which breaks the LTI frame, confuses users, and makes returning to the course navigation messy.

## Installation

The plugin installs like any other Moodle activity module. The contents of this
repository are the plugin itself and must live in `mod/profileupdate`.

### Using Git

```bash
cd /path/to/moodle
git clone https://github.com/<owner>/moodle-mod_profileupdate.git mod/profileupdate
```

### From a ZIP archive

1. Download the plugin ZIP.
2. Extract it so that its files end up in `mod/profileupdate` (the folder must be
   named `profileupdate`).

### Complete the installation

1. Log in to Moodle as an administrator and go to
   **Site administration ▸ Notifications**, or run the CLI upgrade:

   ```bash
   php admin/cli/upgrade.php
   ```

2. Follow the prompts to complete the database upgrade. This registers the
   activity and its capabilities.

After installation the **Profile Update** activity becomes available in the
course activity chooser.

## Configuration

There are no site-wide settings to configure. Everything is configured per
activity instance on Moodle's standard activity settings form.

To add and configure an instance:

1. Turn editing on in a course and choose **Add an activity or resource ▸
   Profile Update**.
2. Give the activity a **Name** and, optionally, a **Description**.
3. In the **Profile fields to update** section, use the multi-select
   autocomplete to choose one or more fields participants should be able to
   update. Two groups are offered:
   - **Standard fields** — built-in Moodle user fields such as First name,
     Email address, Country, Timezone, Institution, and so on.
   - **Custom fields** — any site-defined custom user profile fields
     (`user_info_field`). If the site has no custom profile fields, this group is
     omitted.
4. Save the activity.

Notes:

- Each selected field is stored as a `type|name` identifier (e.g.
  `standard|country`, `custom|favcolor`), so standard and custom fields never
  collide.
- The stored order of fields is used as the display order on the participant
  view.
- Selecting **no fields** is allowed; participants then see an informational
  notice instead of a form.
- Editing an existing instance pre-populates the form with its current
  selection.
- Managers and editing teachers can also reach the settings form via the
  **Configure fields to update** shortcut shown on the activity page.

## Usage

When participants open the activity, they see:

1. The activity name and, if set, its description.
2. An editable form containing exactly the profile fields the activity was
   configured to require, pre-filled with the user's current values.

The participant edits the values and clicks **Save**. On success:

- Standard and custom fields are saved for the user.
- A "Your profile has been updated." confirmation is shown.
- A **Continue** button appears, returning the user to the course.
  This makes the next step obvious even when the course index is hidden or the
  course is embedded in an LTI frame.

**Cancel** returns the user to the course without saving.

The form always edits the viewing user's **own** profile and is shown to any
user who can view the activity, not only students.

### Access control

The plugin defines three capabilities:

| Capability                      | Context | Default roles                                     | Purpose                                               |
|---------------------------------|---------|---------------------------------------------------|-------------------------------------------------------|
| `mod/profileupdate:addinstance` | Course  | Editing teacher, Manager                          | Add a Profile Update activity to a course.            |
| `mod/profileupdate:view`        | Module  | Guest, Student, Teacher, Editing teacher, Manager | View the activity and update one's own profile.       |
| `mod/profileupdate:manage`      | Module  | Editing teacher, Manager                          | Configure which profile fields the activity requires. |

## Compatibility

- **Moodle:** requires Moodle version `2025100600` (Moodle 5.1) or later.
- **Plugin release:** `0.1.0` (`MATURITY_ALPHA`).
- **Supported activity features:** `FEATURE_MOD_INTRO`,
  `FEATURE_SHOW_DESCRIPTION`, and `FEATURE_BACKUP_MOODLE2`.
- **License:** GNU GPL v3 or later (see [LICENSE](LICENSE)).
