# Akeneo Labelized Export Bundle

This bundle adds an XLSX export profile that export attributes labels instead of codes.

## Installation

`composer req niji/akeneo-labelized-export-bundle`

In your `app/AppKernel.php` add a line to enable the bundle:

```php
public function registerProjectBundles() {
   return [
       // your app bundles should be registered here,
       .../...
       new Niji\AkeneoLabelizedExportBundle\AkeneoLabelizedExportBundle(),
       .../...
   ];
}
```
