import json
import syslog
import requests
import xmlrpc.client

from . import BaseAccount


class ApiType:
    XML_RPC = "/xmlrpc/"
    JSON_RPC = "/jsonrpc/"


class ApiClient:
    CLIENT_VERSION = "3.1.1"
    API_LIVE_URL = "https://api.domrobot.com"
    API_OTE_URL = "https://api.ote.domrobot.com"

    def __init__(
        self,
        api_url: str = API_OTE_URL,
        api_type=ApiType.XML_RPC,
        language: str = "en",
        client_transaction_id: str = None,
        debug_mode: bool = False,
    ):
        """
        Args:
            api_url: Url of the api.
            api_type: Type of the api. See ApiType class for all types.
            language: Language for api messages and error codes in responses.
            client_transaction_id: Sent with every request to distinguish your api requests in case you need support.
            debug_mode: Whether requests and responses should be printed out.
        """

        self.api_url = api_url
        self.api_type = api_type
        self.language = language
        self.client_transaction_id = client_transaction_id
        self.debug_mode = debug_mode
        self.customer = None
        self.api_session = requests.Session()

    def login(
        self,
        username: str,
        password: str,
        tfa_token: str = None,
    ) -> dict:
        """Performs a login at the api and saves the session cookie for following api calls.

        Args:
            username: Your username.
            password: Your password.
            tfa_token: The current (time-based) 2fa code for this account if 2fa is enabled. Usually a 6-digit number.
        Returns:
            The api response body parsed as a dict.
        Raises:
            Exception: Username and password must not be None.
            Exception: Api requests two factor challenge but no secret is given. Aborting.
        """

        if username is None or password is None:
            raise Exception("Username and password must not be None.")

        params = {"lang": self.language, "user": username, "pass": password}

        login_result = self.call_api("account.login", params)
        if (
            login_result["code"] == 1000
            and "tfa" in login_result["resData"]
            and login_result["resData"]["tfa"] != "0"
        ):
            if tfa_token is not None:
                secret_code = tfa_token
            else:
                raise Exception(
                    "Api requests two factor challenge but token is not given. Aborting."
                )
            unlock_result = self.call_api("account.unlock", {"tan": secret_code})
            if unlock_result["code"] != 1000:
                return unlock_result

        return login_result

    def logout(self):
        """Logs out the user and destroys the session.

        Returns:
            The api response body parsed as a dict.
        """

        logout_result = self.call_api("account.logout")
        self.api_session.close()
        self.api_session = requests.Session()
        return logout_result

    def call_api(self, api_method: str, method_params: dict = None) -> dict:
        """Makes an api call.

        Args:
            api_method: The name of the method called in the api.
            method_params: A dict of parameters added to the request.
        Returns:
            The api response body parsed as a dict.
        Raises:
            Exception: Api method must not be None.
            Exception: Invalid ApiType.
        """

        if api_method is None:
            raise Exception("Api method must not be None.")
        if method_params is None:
            method_params = {}

        if self.customer:
            method_params["subuser"] = self.customer
        if self.client_transaction_id is not None:
            method_params["clTRID"] = self.client_transaction_id

        if self.api_type == ApiType.XML_RPC:
            payload = xmlrpc.client.dumps(
                (method_params,), api_method, encoding="UTF-8"
            ).replace("\n", "")
        elif self.api_type == ApiType.JSON_RPC:
            payload = str(json.dumps({"method": api_method, "params": method_params}))
        else:
            raise Exception("Invalid ApiType.")

        request_mime_type = (
            "application/json" if self.api_type == ApiType.JSON_RPC else "text/xml"
        )
        headers = {
            "Content-Type": "{}; charset=UTF-8".format(request_mime_type),
            "User-Agent": "DomRobot/" + ApiClient.CLIENT_VERSION,
        }

        response = self.api_session.post(
            self.api_url + self.api_type, data=payload.encode("UTF-8"), headers=headers
        )
        response.raise_for_status()

        if self.debug_mode:
            print("Request (" + api_method + "): " + payload)
            print("Response (" + api_method + "): " + response.text)

        if self.api_type == ApiType.XML_RPC:
            return xmlrpc.client.loads(response.text)[0][0]
        elif self.api_type == ApiType.JSON_RPC:
            return response.json()


class InwxNative(BaseAccount):
    _priority = 65535

    _services = {"inwx_native": "api.domrobot.org"}

    def __init__(self, account: dict):
        super().__init__(account)

    @staticmethod
    def known_services():
        return InwxNative._services.keys()

    @staticmethod
    def match(account):
        return account.get("service") in InwxNative._services

    def _get_record_type(self) -> str:
        if self.current_address.find(":") >= 0:
            return "AAAA"
        else:
            return "A"

    def _get_entry(self, hostname: str, result: dict) -> dict | None:
        return next(
            (
                x
                for x in result["resData"]["record"]
                if x["name"] == hostname and x["type"] == self._get_record_type()
            ),
            None,
        )

    def _create_or_update(self, client: ApiClient) -> bool:
        ninfo = client.call_api("nameserver.info", {"domain": self.settings["zone"]})
        if ninfo["code"] != 1000:
            syslog.syslog(
                syslog.LOG_ERR,
                f"Can't query for domain {self.settings['zone']}: {str(ninfo['msg'])}",
            )
            return False
        for hostname in self.settings["hostnames"].split(","):
            hostname: str = hostname
            entry = self._get_entry(hostname, ninfo)
            if entry is not None:
                res = client.call_api(
                    "nameserver.updateRecord",
                    {
                        "id": entry["id"],
                        "type": entry["type"],
                        "content": self.current_address,
                        "ttl": self.settings.get("ttl", 300),
                    },
                )
            else:
                res = client.call_api(
                    "nameserver.createRecord",
                    {
                        "domain": self.settings["zone"],
                        "name": hostname.removesuffix(self.settings["zone"]),
                        "type": self._get_record_type(),
                        "content": self.current_address,
                        "ttl": self.settings.get("ttl", 300),
                    },
                )

            if res["code"] != 1000:
                syslog.syslog(
                    syslog.LOG_ERR,
                    f"Can't create or update entry for {hostname}: {str(res['msg'])}",
                )
                return False

        return True

    def execute(self):
        if super().execute():
            client = ApiClient(
                api_url=ApiClient.API_LIVE_URL, api_type=ApiType.JSON_RPC
            )
            login_ok = False
            reason = ""
            try:
                res = client.login(
                    self.settings.get("username", ""), self.settings.get("password", "")
                )
                if res["code"] == 1000:
                    login_ok = True
                else:
                    reason = res["msg"]
            except Exception as e:
                reason = str(e)

            if not login_ok:
                syslog.syslog(
                    syslog.LOG_ERR,
                    f"Account {self.description} error while logging in: {reason}",
                )
                return False

            try:
                return self._create_or_update(client)
            finally:
                client.logout()
