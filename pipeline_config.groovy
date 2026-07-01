jte {
    pipeline_template = "zip_library"
}
libraries{
    php
    s3
}
agent = "op_jenkins_mx_dev_slave_2023_php"
init_agent = "op_jenkins_mx_dev_slave_2023_php"
git_credentials_id = "jenkins-github-latam-ct"

// Variables a usar por ambiente, en este ejemplo se dejan como ignorados todos los ambientes, 
// se deben de configurar acorde a lo que se tenga
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
        source = '/home/ec2-user/html/wp-content/plugins/openpay-woocommerce-cards/openpay-cards.zip'
        destination = 'php/dev/openpay-woocommerce-cards-${projectVersion}-${branchName}-${buildNumber}.zip'
    }
}

release_active = true
automatic_versioning = true

bitbucket_oauth_enabled = false