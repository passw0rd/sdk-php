<?php
/**
 * Copyright (C) 2015-2020 Virgil Security Inc.
 *
 * All rights reserved.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are
 * met:
 *
 *     (1) Redistributions of source code must retain the above copyright
 *     notice, this list of conditions and the following disclaimer.
 *
 *     (2) Redistributions in binary form must reproduce the above copyright
 *     notice, this list of conditions and the following disclaimer in
 *     the documentation and/or other materials provided with the
 *     distribution.
 *
 *     (3) Neither the name of the copyright holder nor the names of its
 *     contributors may be used to endorse or promote products derived from
 *     this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE AUTHOR ''AS IS'' AND ANY EXPRESS OR
 * IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE IMPLIED
 * WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE AUTHOR BE LIABLE FOR ANY DIRECT,
 * INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL DAMAGES
 * (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION)
 * HOWEVER CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT,
 * STRICT LIABILITY, OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING
 * IN ANY WAY OUT OF THE USE OF THIS SOFTWARE, EVEN IF ADVISED OF THE
 * POSSIBILITY OF SUCH DAMAGE.
 *
 * Lead Maintainer: Virgil Security Inc. <support@virgilsecurity.com>
 */

namespace Virgil\PureKit\Pure\Storage;

use Virgil\PureKit\Pure\Exception\ErrorStatus\PureStorageGenericErrorStatus;
use Virgil\PureKit\Pure\Exception\MariaDbSqlException;
use Virgil\PureKit\Pure\Exception\PureStorageGenericException;
use Virgil\PureKit\Pure\Model\UserRecord;
use Virgil\PureKit\Pure\PureModelSerializer;
use Virgil\PureKit\Pure\PureModelSerializerDependent;

class MariaDbPureStorage implements PureStorage, PureModelSerializerDependent
{
    private $host;
    private $userName;
    private $password;
    private $dbName;
    private $pureModelSerializer;

    public function __construct(string $host, string $userName, string $password, string $dbName)
    {
        $this->host = $host;
        $this->userName = $userName;
        $this->password = $password;
        $this->dbName = $dbName;
    }

    public function getPureModelSerializer(): PureModelSerializer
    {
        return $this->pureModelSerializer;
    }

    public function setPureModelSerializer(PureModelSerializer $pureModelSerializer): void
    {
        $this->pureModelSerializer = $pureModelSerializer;
    }

    private function getConnection()
    {
        return mysqli_connect($this->host, $this->userName, $this->password, $this->dbName);
    }

    public function insertUser(UserRecord $userRecord): void
    {
        $protobuf = $this->getPureModelSerializer()->serializeUserRecord($userRecord);

        $conn = $this->getConnection();

        $stmt = $conn->prepare("INSERT INTO virgil_users (" .
            "user_id," .
            "phe_record_version," .
            "protobuf) " .
            "VALUES (?, ?, ?);");

        $stmt->bind_param("sss", $userRecord->getUserId(), $userRecord->getRecordVersion(), $protobuf);

        try {
            $stmt->execute();
        }
        catch (\mysqli_sql_exception $exception) {
            if ($exception->getCode() != 1062) {
                throw $exception;
            }
            throw new PureStorageGenericException(PureStorageGenericErrorStatus::USER_ALREADY_EXISTS());
        }
    }

    public function cleanDb(): void
    {
        $conn = $this->getConnection();
        $stmt = $conn->prepare("DROP TABLE IF EXISTS virgil_grant_keys, virgil_role_assignments, virgil_roles, virgil_keys, virgil_users;")->execute();
        $stmt = $conn->query("DROP EVENT IF EXISTS delete_expired_grant_keys;");
    }

    public function initDb(int $cleanGrantKeysIntervalSeconds): void {
        $conn = $this->getConnection();

        $stmt = $conn->query("CREATE TABLE virgil_users (" .
            "user_id CHAR(36) NOT NULL PRIMARY KEY," .
            "phe_record_version INTEGER NOT NULL," .
            "    INDEX phe_record_version_index(phe_record_version)," .
            "    UNIQUE INDEX user_id_phe_record_version_index(user_id, phe_record_version)," .
            "protobuf VARBINARY(2048) NOT NULL" .
            ");");
        if (!$stmt)
            throw new MariaDbSqlException($conn->error, $conn->errno);

        $stmt = $conn->query("CREATE TABLE virgil_keys (" .
            "id INT NOT NULL AUTO_INCREMENT PRIMARY KEY," .
            "user_id CHAR(36) NOT NULL," .
            "    FOREIGN KEY (user_id)" .
            "        REFERENCES virgil_users(user_id)" .
            "        ON DELETE CASCADE," .
            "data_id VARCHAR(128) NOT NULL," .
            "    UNIQUE INDEX user_id_data_id_index(user_id, data_id)," .
            "protobuf VARBINARY(32768) NOT NULL /* FIXME Up to 128 recipients */" .
            ");");
        if (!$stmt)
            throw new MariaDbSqlException($conn->error, $conn->errno);

        $stmt = $conn->query("CREATE TABLE virgil_roles (".
            "id INT NOT NULL AUTO_INCREMENT PRIMARY KEY,".
            "role_name VARCHAR(64) NOT NULL,".
            "    INDEX role_name_index(role_name),".
            "protobuf VARBINARY(196) NOT NULL".
            ");");
        if (!$stmt)
            throw new MariaDbSqlException($conn->error, $conn->errno);

        $stmt = $conn->query("CREATE TABLE virgil_role_assignments (" .
            "id INT NOT NULL AUTO_INCREMENT PRIMARY KEY," .
            "role_name VARCHAR(64) NOT NULL," .
            "    FOREIGN KEY (role_name)" .
            "        REFERENCES virgil_roles(role_name)" .
            "        ON DELETE CASCADE," .
            "user_id CHAR(36) NOT NULL," .
            "    FOREIGN KEY (user_id)" .
            "        REFERENCES virgil_users(user_id)" .
            "        ON DELETE CASCADE," .
            "    INDEX user_id_index(user_id)," .
            "    UNIQUE INDEX user_id_role_name_index(user_id, role_name)," .
            "protobuf VARBINARY(1024) NOT NULL" .
            ");");
        if (!$stmt)
            throw new MariaDbSqlException($conn->error, $conn->errno);

        $stmt = $conn->query("CREATE TABLE virgil_grant_keys (" .
            "user_id CHAR(36) NOT NULL," .
            "    FOREIGN KEY (user_id)" .
            "        REFERENCES virgil_users(user_id)" .
            "        ON DELETE CASCADE," .
            "key_id BINARY(64) NOT NULL," .
            "expiration_date TIMESTAMP NOT NULL," .
            "    INDEX expiration_date_index(expiration_date)," .
            "protobuf VARBINARY(1024) NOT NULL," .
            "    PRIMARY KEY(user_id, key_id)" .
            ");");
        if (!$stmt)
            throw new MariaDbSqlException($conn->error, $conn->errno);

        $stmt = $conn->query("SET @@global.event_scheduler = 1;");
        if (!$stmt)
            throw new MariaDbSqlException($conn->error, $conn->errno);

        $stmt = $conn->query("CREATE EVENT delete_expired_grant_keys ON SCHEDULE EVERY $cleanGrantKeysIntervalSeconds SECOND" .
            "    DO" .
            "        DELETE FROM virgil_grant_keys WHERE expiration_date < CURRENT_TIMESTAMP;");
        if (!$stmt)
            throw new MariaDbSqlException($conn->error, $conn->errno);
    }
}