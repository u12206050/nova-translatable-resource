# Nova Translatable Resource

Patches an issue when searching nova models that make use of laravel translatable

Used commonly with:

 - astrotomic/laravel-translatable
 - day4/switch-locale


## Install

`composer required day4/nova-translatable-resource`


## Usage

Switch out the Nova Resource for this one

```
# use Laravel\Nova\Resource;
use Day4\Nova\TranslatableResource;

class Post extends TranslatableResource
{
```
