# Content Export Plugin for Moodle

A Moodle local plugin that provides web service APIs to export course structure, content, files, and metadata in a structured JSON format. 
This plugin is designed for data migration, backup purposes, and integration with external systems.

## Requirements

- Moodle 4.0 or higher (tested with moodle 4.5, 5.0)
- Web services enabled

## Installation

1. Go to Site Administration → Plugins → Install plugins
2. Upload the plugin ZIP file
3. Follow the prompts to install
4. Configure web services (see Configuration below)

## Configuration

### 1. Enable Web Services

Navigate to **Site Administration → Server → Web services**:

1. **Enable web services**: Check "Enable web services"
2. **Enable protocols**: Enable "REST protocol"
3. **Create a service**:
   - Go to "External services"
   - Add the "Content Export Service" if it was not created automatically
   - **Important**: Check "Can download files" in the "Show more" Section to enable file downloads through the API

### 2. Create Web Service User & Token

**Note**: Only site administrators or users with the `moodle/webservice:createtoken` capability can create webservice tokens.

1. **Create/select user** for API access
2. **Assign appropriate role** (see Permissions section)
3. **Generate token** (requires admin privileges):
   - Go to Site Administration → Server → Web services → Manage tokens
   - Create token for your user or the API user
   - Select "Content Export Service"
   - Save the generated token securely

### 3. Test Configuration

```bash
curl -X POST "https://your-moodle-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_contentexport_export_course" \
  -d "moodlewsrestformat=json" \
  -d "courseid=2"
```

## API Reference

### Export Single Course

**Function**: `local_contentexport_export_course`

**Parameters**:
- `courseid` (int, required): Course ID to export

**Example**:
```bash
curl -X POST "https://your-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_contentexport_export_course" \
  -d "moodlewsrestformat=json" \
  -d "courseid=5"
```

### Export Multiple Courses (Paginated)

**Function**: `local_contentexport_export_all_courses`

**Parameters**:
- `include_hidden` (bool, optional): Include hidden courses (default: false)
- `category_id` (int, optional): Filter by category ID, 0 = all (default: 0)
- `offset` (int, optional): Starting position for pagination (default: 0)
- `limit` (int, optional): Maximum courses per page, 0 = no limit (default: 50)
- `include_non_enrolled` (bool, optional): Include non-enrolled courses (default: false, requires special permissions)

**Examples**:

```bash
# Get first 25 courses
curl -X POST "https://your-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_contentexport_export_all_courses" \
  -d "moodlewsrestformat=json" \
  -d "limit=25"

# Get courses 26-50 from category 3
curl -X POST "https://your-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_contentexport_export_all_courses" \
  -d "moodlewsrestformat=json" \
  -d "offset=25" \
  -d "limit=25" \
  -d "category_id=3"

# Include hidden and non-enrolled courses (admin only)
curl -X POST "https://your-site.com/webservice/rest/server.php" \
  -d "wstoken=YOUR_TOKEN" \
  -d "wsfunction=local_contentexport_export_all_courses" \
  -d "moodlewsrestformat=json" \
  -d "include_hidden=1" \
  -d "include_non_enrolled=1"
```

## Response Format

### Course Export Response

```json
{
  "course": {
    "id": 5,
    "fullname": "Introduction to Programming",
    "shortname": "PROG101",
    "description": "<p>Course description...</p>",
    "category": "Computer Science",
    "course_url": "https://moodle.site.com/course/view.php?id=5",
    "sections": [
      {
        "id": 15,
        "name": "Week 1: Getting Started",
        "summary": "<p>Introduction to programming concepts</p>",
        "section_number": 1,
        "section_url": "https://moodle.site.com/course/view.php?id=5#section-1",
        "activities": [
          {
            "id": 42,
            "name": "Programming Basics",
            "type": "book",
            "description": "<p>Learn fundamental concepts...</p>",
            "activity_url": "https://moodle.site.com/mod/book/view.php?id=42",
            "files": [
              {
                "id": 123,
                "filename": "intro.pdf",
                "filepath": "/",
                "filesize": 1048576,
                "mimetype": "application/pdf",
                "timecreated": 1627891200,
                "timemodified": 1627891200,
                "download_url": "https://moodle.site.com/pluginfile.php/...",
                "hash": "a1b2c3d4e5f6..."
              }
            ],
            "urls": [
              {
                "url": "https://example.com/resource",
                "display_type": 0,
                "is_primary_url": true,
                "found_in": "activity_url"
              }
            ],
            "content_data": {
              "type": "book",
              "chapters": [
                {
                  "id": 1,
                  "title": "Chapter 1",
                  "content": "<p>Chapter content...</p>",
                  "pagenum": 1,
                  "subchapter": false,
                  "hidden": false
                }
              ]
            }
          }
        ]
      }
    ]
  },
  "exported_at": "2025-07-25T10:30:00+00:00"
}
```

### Paginated Response

```json
{
  "courses": [
    {
      "id": 5,
      "fullname": "Introduction to Programming",
      "shortname": "PROG101",
      "description": "<p>Course description...</p>",
      "category": "Computer Science",
      "course_url": "https://moodle.site.com/course/view.php?id=5",
      "sections": [...],
    }
  ],
  "pagination": {
    "total_courses": 250,
    "returned_courses": 50,
    "offset": 0,
    "limit": 50,
    "has_more": true,
    "next_offset": 50
  },
  "exported_at": "2025-07-25T10:30:00+00:00"
}
```

## Supported Activity Types

The plugin extracts content and files from various Moodle activity types:

- **Resource**: Files and documents
- **Folder**: File collections
- **Book**: Chapters and content
- **Page**: HTML content plus files from the `mod_page/content` filearea (including images and H5P content embedded via the editor) and intro attachments
- **URL**: External links
- **Glossary**: Terms and definitions
- **Assignment**: Instructions and files
- **SCORM**: Packages and metadata
- **Quiz**: Questions, attempts,...
- **H5P Activity** (`h5pactivity`): Deployed `.h5p` package file from the `mod_h5pactivity/package` filearea plus intro attachments

## Security Notes
- URLs are not public and require authentication
- File access follows Moodle's permission system
- URLs remain valid as long as user has access to the course
