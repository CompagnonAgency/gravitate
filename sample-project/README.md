# Sample project

> Polarbit's Grav generator for Gravitate.

## Usage

Install dependencies

```
$ npm install
```

Compile Javascript and SCSS.

```
gulp
```

Preferably use MAMP or any other Apache server.

## Sync with remote site

    Manually download the /user folder

## Deployment

Make sure you created a `phploy.ini` and make sure both Grav and it's
dependencies are up to date. See `phploy.ini.sample` for an example.

**Always pull the remote site first before you deploy**

    phploy -s production

## Updating and installing dependencies

#### Install the plugin and theme dependencies

    bin/grav install

#### Update Grav

    bin/gpm selfupgrade

#### Update plugins and themes

    bin/gpm update

## License

(c) Polarbit.co
