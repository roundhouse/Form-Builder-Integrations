# Form Builder Integrations Changelog

## 1.0.16 - 2020-07-06

### Improved
- Updated input styles

## 1.0.15 - 2020-07-03

### Improved
- Updated styles to better fit with Craft UI

## 1.0.12 - 2019-04-26

### Fixed
- Bump omnipay-converge version to 1.0.6

## 1.0.11 - 2019-04-26

### Fixed
- Fixing masking for Credit Cards and CVV

## 1.0.10 - 2019-04-26

### Fixed
- Bump omnipay-converge version 

## 1.0.9 - 2019-04-26

### Improved
- Better error handling on API failures

## 1.0.8 - 2019-04-26

### Fixed
- Missing $form in CC masking method

## 1.0.7 - 2019-04-11

### Added
- Echeck/ACH transaction types and CreditCard refactoring

### Fixed
- Fixed undefined type key error for already created Converge integrations

## 1.0.6 - 2019-02-05

### Fixed
- Fixed a converge integration bug
- Added masking to CC and CVC regardless if transaction is successful or not

## 1.0.5 - 2019-02-05

### Fixed
- Added friendly message if credentials are invalid

## 1.0.4 - 2019-01-29

### Added
- Added afterSave event to converge integration

## 1.0.3 - 2019-01-02

### Improved
- Added friendly validation for CC number (if used as PlainText), stripped white spaces
- Added friendly validation for Amount field

## 1.0.2 - 2018-12-20

### Fixed
- Converge integration now accepts Dropdown values for expiration date


## 1.0.1 - 2018-12-19

### Improved
- Updated fields to mask out CC and remove CVV values
- Updated db column types

## 1.0.0 - 2018-12-11

### Added
- Initial release
