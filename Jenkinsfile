// =============================================================================
// Pipeline de deploy de LendusFind para Jenkins.
//
// Asumimos que Jenkins corre en el mismo server que la app (puerto 8080).
// El usuario `jenkins` necesita:
//   1. Acceso de lectura al repo Git (SSH key configurada en Credentials).
//   2. Acceso de escritura a /home/lendus/laravelfiles_moneycapital
//      (o el sudo correspondiente; lo más simple es que el job corra como
//      el usuario `lendus` con `sudo su - lendus`).
//   3. Permiso sudo NOPASSWD para los servicios. En /etc/sudoers.d/lendusfind:
//        jenkins ALL=(root) NOPASSWD: /usr/bin/systemctl restart httpd, /usr/bin/systemctl reload httpd, /usr/bin/systemctl restart lendusfind-reverb
//
// Triggers:
//   - Manual desde Jenkins UI ("Build with Parameters")
//   - Webhook GitLab/GitHub (push a branch protegida → deploy automático)
//
// Parámetros del build:
//   - REF        Branch, tag o commit a deployar (default: feat/moneycapital-onboarding)
//   - SKIP_TESTS Si los tests deben saltearse (default: false)
// =============================================================================

pipeline {
    agent any

    options {
        timestamps()
        ansiColor('xterm')
        timeout(time: 15, unit: 'MINUTES')
        buildDiscarder(logRotator(numToKeepStr: '20'))
        disableConcurrentBuilds()  // un solo deploy a la vez
    }

    parameters {
        string(name: 'REF', defaultValue: 'feat/moneycapital-onboarding', description: 'Branch, tag o commit a deployar')
        booleanParam(name: 'SKIP_TESTS', defaultValue: false, description: 'Saltar la fase de tests (deploy de emergencia)')
        booleanParam(name: 'DRY_RUN', defaultValue: false, description: 'Solo simular (no aplica cambios)')
    }

    environment {
        APP_DIR      = '/home/lendus/laravelfiles_moneycapital'
        PHP_BIN      = 'ea-php82'
        COMPOSER_BIN = '/usr/local/bin/composer'
        // Identidad del run para el changelog/Slack
        DEPLOY_ID    = "${BUILD_NUMBER}-${env.GIT_COMMIT?.take(7) ?: 'unknown'}"
    }

    stages {
        stage('Checkout') {
            steps {
                checkout([
                    $class: 'GitSCM',
                    branches: [[name: "${params.REF}"]],
                    userRemoteConfigs: [[
                        url: 'https://github.com/Hernai/lendus_find.git',
                        // O GitLab si prefieres usar ese remote:
                        // url: 'git@gitlab.com:ITSolutionMX/lendus-find-mobile.git',
                        // credentialsId: 'gitlab-deploy-key',
                    ]],
                ])
                sh 'git log -1 --pretty=format:"%h %an %s"'
            }
        }

        stage('Tests') {
            when {
                expression { !params.SKIP_TESTS }
            }
            steps {
                dir('backend') {
                    sh '''
                        if [ -f vendor/bin/phpunit ]; then
                            ${PHP_BIN} vendor/bin/phpunit --testsuite=Unit
                        else
                            echo "phpunit no instalado, salto tests"
                        fi
                    '''
                }
            }
        }

        stage('Deploy Backend') {
            steps {
                script {
                    if (params.DRY_RUN) {
                        echo "DRY_RUN: simulando deploy de ${params.REF}"
                    } else {
                        // Ejecutar como el usuario lendus (dueño del directorio app)
                        sh """
                            sudo -u lendus bash -c '
                                cd ${env.APP_DIR}
                                ./scripts/deploy-backend.sh ${params.REF}
                            '
                        """
                    }
                }
            }
        }

        stage('Health Check') {
            steps {
                sh '''
                    sleep 3
                    HTTP=$(curl -s -o /dev/null -w "%{http_code}" "https://apifind.lendus.app/api/v2/public/health")
                    BODY=$(curl -s "https://apifind.lendus.app/api/v2/public/health")
                    echo "HTTP $HTTP"
                    echo "Body: $BODY"
                    if [ "$HTTP" != "200" ]; then
                        echo "Health check FAILED"
                        exit 1
                    fi
                '''
            }
        }
    }

    post {
        success {
            echo "✓ Deploy ${env.DEPLOY_ID} → ${params.REF} OK"
            // Si tienes Slack o webhook de notificación:
            // slackSend(color: 'good', message: "Deploy ${env.DEPLOY_ID} → ${params.REF} OK")
        }
        failure {
            echo "✗ Deploy ${env.DEPLOY_ID} FAILED — revisar logs"
            // slackSend(color: 'danger', message: "Deploy ${env.DEPLOY_ID} FAILED")
        }
        always {
            cleanWs()
        }
    }
}
