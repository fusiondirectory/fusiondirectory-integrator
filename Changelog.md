## %"FusionDirectory Integrator 1.2" - 2024-12-16

### Added

#### fusiondirectory-integrator
- fusiondirectory-integrator#33 [Integrator] - AUDIT - Removal of audit after set period - new lib
- fusiondirectory-integrator#34 [Integrator] - Create MailLib to be used by orchestrator instead of the mailController.
- fusiondirectory-integrator#35 [Integrator] - Mail - Adds conditions to mail security and authentication
- fusiondirectory-integrator#36 [Integrator] - Add doxyfile
- fusiondirectory-integrator#40 [Integrator] - Maillib - fallback to base 64 is not required
- fusiondirectory-integrator#41 [Integrator] - Web Service call requires a definition of an HTTP_AGENT

### Changed

#### fusiondirectory-integrator
- fusiondirectory-integrator#30 [Integrator] - Refactor of current logic and creation of fusiondirectory library.

### Fixed

#### fusiondirectory-integrator
- fusiondirectory-integrator#31 [Integrator] - When webservice call is triggered with a DN containing a space, result is NULL

## %"FusionDirectory Integrator 1.1" - 2024-04-05

### Added

#### fusiondirectory-integrator
- fusiondirectory-integrator#26 [Rest] - import library WebServiceCall from fusiondirectory-orchestrator

## %"FusionDirectory Integrator 1.0" - 2023-04-24

### Added

#### fusiondirectory-integrator
- fusiondirectory-integrator#3 [Integrator] - Move of CLI and LDAP libraries within Integrator - First step towards Integrator
- fusiondirectory-integrator#7 [Integrator] - Merge of branch 0.9 towards main

### Changed

#### fusiondirectory-integrator
- fusiondirectory-integrator#4 [Integrator] - Modifies the namespaces for some CLI libraries
- fusiondirectory-integrator#6 [Integrator] - Change SecretBox to have proper file extension in order to be autoloaded properly.

### Fixed
#### fusiondirectory-integrator
- fusiondirectory-integrator#14 [integrator] - Fixes the verbose error when not set.
