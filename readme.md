# TestGator

TestGator, a tool for generating testing plans for software projects. It is designed to be used by software developers
and testers to help them create test plans that are comprehensive and effective. TestGator is a web-based tool that can
be accessed from any device with an internet connection. It is easy to use and can be customized to meet the needs of
any software project. TestGator is a powerful tool that can help you create test plans that are thorough, accurate, and
efficient.

## Why worker mode is not enabled on TestGator ?

Because of LDAP ext we can't run the worker mode. Because the connection to the LDAP server will fail at some point.

`see : https://github.com/dunglas/frankenphp/issues/457`
