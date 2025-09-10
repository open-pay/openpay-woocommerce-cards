jte {
    pipeline_template = "zip_library"
}
libraries{
    php
    s3
}
git_ssh_credentials_id = "github_openpay"
// Variables a usar por ambiente, en este ejemplo se dejan como ignorados todos los ambientes, se deben de configurar acorde a lo que se tenga
application_environments{
    sandbox{
        ignore = true
    }
    prod{
        ignore = true
    }
    dev{
        ignore = false
        bucket = 'openpay-development-wars'
        source = 'openpay-woocommerce-cards.zip'
        destination = 'php/dev/openpay-woocommerce-cards-${projectVersion}-${branchName}-${buildNumber}.zip'
    }
}

release_active = true
automatic_versioning = true