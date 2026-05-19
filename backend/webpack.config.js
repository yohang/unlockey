const Encore = require('@symfony/webpack-encore');
const fs = require('fs');

if (!Encore.isRuntimeEnvironmentConfigured()) {
    Encore.configureRuntimeEnvironment(process.env.NODE_ENV || 'dev');
}

Encore
    .setOutputPath('public/build/')
    .setPublicPath('/build')

    .addEntry('app', './assets/app.ts')
    .addStyleEntry('bootstrap', './assets/styles/bootstrap.scss')

    .splitEntryChunks()

    // enables the Symfony UX Stimulus bridge (used in assets/bootstrap.js)
    .enableStimulusBridge('./assets/controllers.json')
    .enableSingleRuntimeChunk()

    .cleanupOutputBeforeBuild()
    .enableBuildNotifications()
    .enableSourceMaps(!Encore.isProduction())
    .enableVersioning(Encore.isProduction())

    // configure Babel
    // .configureBabel((config) => {
    //     config.plugins.push('@babel/a-babel-plugin');
    // })

    // enables and configure @babel/preset-env polyfills
    .configureBabelPresetEnv((config) => {
        config.useBuiltIns = 'usage';
        config.corejs = '3.23';
    })

    .copyFiles({
        from: './assets/images',
        to:   'images/[path][name].[hash:8].[ext]',
    })

    .enableSassLoader((sassLoaderOptionsCallback) => {
        return {
            ...sassLoaderOptionsCallback,
            sassOptions: {
                ...sassLoaderOptionsCallback.sassOptions,
                quietDeps: true,
            },
        };
    })
    .enableTypeScriptLoader()
    .enableIntegrityHashes(Encore.isProduction())


    .configureDevServerOptions(options => {
        return {
            ...options,
            allowedHosts: "all",
            host: '0.0.0.0',
            server: {
                type: 'https',
                options: {
                    key:  fs.readFileSync('/app/infra/docker/php/tls/key.pem'),
                    cert: fs.readFileSync('/app/infra/docker/php/tls/cert.pem'),
                },
            },
        };
    })
;

module.exports = Encore.getWebpackConfig();
