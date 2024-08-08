libraries{
    php
    s3
}

// Variables a usar por ambiente, en este ejemplo se dejan como ignorados todos los ambientes, se deben de configurar acorde a lo que se tenga
application_environments{
    sandbox{
        ignore = true
    }
    prod{
        ignore = true
    }
    dev{
        ignore = true
    }
}
