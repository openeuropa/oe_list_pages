# OE List Pages Link List Displays

This module furthers the integration with the `OE Link Lists` component by
taking over the rendering of the list pages and using the
`Display` plugins made available for link lists.

## How to use

Install the module and it will directly start working.

When you create a list page, you will have a mandatory `Display` select where
you will need to pick one of the available link list display plugins.

List pages created before the module was installed will continue to render using
the default view mode.

## How it works

The module acts in two places.

First, it alters the `ListPage` entity meta configuration form to present the user
with the option to pick and configure the Display plugin to use.
This choice is then stored in the `extra` configuration key of the list page.

Second, the module takes over the `ListBuilder` object responsible for rendering
the list and uses the chosen Display plugin to actually build the list.
