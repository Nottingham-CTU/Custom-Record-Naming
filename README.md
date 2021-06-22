# Custom-Record-Naming

This REDCap module provides options to adjust how records are automatically numbered.

If enabled on a project where DAGs are used, this module also provides DAG specific public survey
URLs.


## Project-level configuration options

### Restrict DAG name format
This optional setting takes a regular expression against which all DAG names will be validated.
The user will be presented with an error when creating or renaming a DAG with an invalid name.

As this field is optional, it can be left blank. This allows users to create DAGs with any name as
normal. If a DAG is created with a name that is not allowed by any of the record naming schemes,
then users in that DAG will be unable to create any records.

Note that it is not necessary to enclose regular expressions in delimiters.

### Information about DAG name format
Any text entered here will be displayed on the DAGs page as an information box. This can be useful
to explain any restrictions on DAG names.

HTML `<a>` (link) and `<b>` (bold) tags are supported in the information text.

### Record numbering
This defines how the project's records are numbered.

* **Project-wide** will maintain only one record counter for the entire project. This means that
  every record number will be unique. This is the REDCap default behaviour when DAGs are not used.
  In a multi-arm project, each arm can still format the number differently according to the naming
  scheme.
* **Per arm** will maintain a separate record counter for each arm. This means that the first record
  *in each arm* will be record number 1 (or the defined starting number). In the naming scheme for
  each arm, a prefix, separator and/or suffix must be defined so that the records for each arm are
  distinct.
* **Per DAG** will maintain a separate record counter for each DAG. This means that the first record
  *in each DAG* will be record number 1 (or the defined starting number). This is the REDCap default
  behaviour when DAGs are used. If per DAG numbering is used, the record name type for each naming
  scheme must include the DAG (i.e. *record number only* is not permitted).
* **Per arm and DAG** will maintain a separate record counter for each *arm and DAG combination*.
  The limitations of *per arm* and *per DAG* numbering apply.

### Custom naming scheme
The following options apply to each naming scheme defined.

### Target arm
This is the arm of the project to which the naming scheme applies. You should define a naming scheme
for each arm. If the project is not longitudinal or only has one arm, there will be only one arm in
the list, which you should select here.

### Record name type
This defines how the record name is generated and must consist of a combination of the following
options:

* Record number
  * Picked from the appropriate record counter, in accordance with the numbering setting.
* User supplied
  * A value which the user will be prompted for when they create the record.
* DAG
  * The DAG name (or a subset of the DAG name as defined by the DAG name format and subpattern).
  * DAG cannot be selected on its own.

Select one option or a combination in the desired order. The record name will be generated using the
components in the selected order, separated by the separator value.

### Prompt for user supplied name
The text which will be displayed to the user when they are prompted for the user supplied component
of the record name. This is required if the user specified option is used.

### User supplied name format
This defines the format (regular expression) which the user supplied name must match in order to be
accepted. This is required if the user specified option is used.

### Starting number
This is the first record number that will be used. If this is not set, records will be numbered
starting from 1.

If per arm numbering is not used, the arm of the first record to be added for the project (if
project wide numbering) or the DAG (if per DAG numbering) will decide the starting number used.

### Zero pad record number
This allows the record number to be a fixed length. The number will be left padded with zeros to
make it the correct length. This applies only to the record number portion of the record name,
adding the DAG name, prefix, separator and suffix will increase the record name length.

### Accept DAG name format / DAG format subpattern
This defines the DAG name format which can be used for records in this arm. Users in a DAG with a
name not matching this regular expression (or not in a DAG at all) cannot add records to the arm.

The portion of the DAG name which matches the regular expression will be used in the record name.
The DAG format subpattern option must be set to an integer can be used to further narrow down the
section of the DAG name which is used.

If the DAG format subpattern is set to `0`, then the portion of the DAG name which matches the
entire DAG format regular expression is used. Note that this will not necessarily be the entire
DAG name.

If the DAG format subpattern is set to `1` or greater, then only the portion of the DAG name which
is denoted by the corresponding set of parentheses (`(`,`)`) is used. Subpatterns are counted from
the left by the opening parenthesis (`(`).

### Record name prefix
If set, this value is prepended at the start of the record name.

### Record name separator
If set, this value is inserted between each component of the record name (record number, DAG name,
user supplied). If only one component is used, this value is ignored.

### Record name suffix.
If set, this value is appended at the end of the record name.

### Counter overview
Administrators have access to the counter overview, which is accessible via a link in the module
configuration. This provides an interface to view and edit the record counters which determine the
new record names.


## Regular expressions
This is a basic overview of regular expressions. It may be useful for configuring this module, but
it is not intended to be a comprehensive guide.

* `^` If used at the start of the expression, denotes the start of the string. If used at the start
  of a character class, negates the class.
* `$` If used at the end of the expression, denotes the end of the string.
* `\` Can be used to escape special characters, so they can be used literally.
* `[` and `]` Denote a character class. A group of characters can be used to denote one of those
  characters (e.g. `[abc]` matches one of *a*, *b* or *c*), or a range can be used (e.g. `[a-z]`
  matches any lowercase letter). To explicitly include a dash as a character in the class, place it
  at the end before the closing bracket. Remember that `^`, if used at the start, negates the class
  (e.g. `[^a-z]` matches any character which is *not* a lowercase letter).
* `(` and `)` Denote a subpattern. This can be useful to identify a smaller portion of the matched
  string (see the subpattern setting), or it can be combined with one of the following operators.
* `?` Matches the preceding character/class/subpattern 0 or 1 times (i.e. makes it optional).
* `+` Matches the preceding character/class/subpattern 1 or more times.
* `*` Matches the preceding character/class/subpattern 0 or more times.
* `{n}` Matches the preceding character/class/subpattern n times (where n is an integer).
* `{m,n}` Matches the preceding character/class/subpattern between m and n times (where m and n are
  integers and m is less than n). Omit one of the numbers to set just a lower bound (i.e. `{m,}`)
  or upper bound (i.e. `{,n}`).

### Regular expression examples
The following examples can be entered into the *Accept DAG name format* field. The indicated
subpattern value should be entered into the *DAG format subpattern* field. Optionally, entering the
regular expression into the *Restrict DAG name format* field will prevent DAG names which do not
match the format from being created.

If you are naming your DAGs with numeric prefixes, these regular expressions will use only the
prefix as the DAG identifier in the record name (prefixes are separated from the rest of the DAG
name by a space):
* 2 digit prefix: `^([0-9]{2})[ ]` &nbsp;(subpattern=1)
* 3 digit prefix: `^([0-9]{3})[ ]` &nbsp;(subpattern=1)
* 4 digit prefix: `^([0-9]{4})[ ]` &nbsp;(subpattern=1)
* Arbitrary length prefix: `^([0-9]+)[ ]` &nbsp;(subpattern=1)

The following regular expression will take part of the DAG name and use it as the DAG identifier
in the record name:
* First word (all prior to first space): `^([^ ]+)( |$)` &nbsp;(subpattern=1)



