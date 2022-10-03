const FACEBOOK_APP_ID = document.getElementById('FACEBOOK_APP_ID').value;
const FACEBOOK_GRAPH_URL = document.getElementById('FACEBOOK_GRAPH_URL').value;
const FACEBOOK_GRAPH_VERSION = document.getElementById('FACEBOOK_GRAPH_VERSION').value;

let pageDomTemplate = `
<tr id="block">
    <td>
        <img class="ui avatar image mini page_image" id="page_image" src="PAGE_IMAGE_URL" />
        <input style="display: none;" id="page_id" value="PAGE_ID"/>
        <span style="padding-left: 10px; font-size: large;" id="page_title"> PAGE_TITLE </span>
    </td>
    <td style="text-align: center">Connected</td>
    <td><button class="ui green button fluid">Conect</button></td>
</tr>
`;

window.fbAsyncInit = function() {
    FB.init({
        appId      : FACEBOOK_APP_ID,
        xfbml      : true,
        version    : FACEBOOK_GRAPH_VERSION
    });
    checkLoginState();
};

function logIn() {
    FB.login(function(response) {
        if (response.status === 'connected') {
            const user = response.authResponse;

            getUserPages(user.userID, user.accessToken);
        } else {
            console.log('not logged in', response);
        }
    });
}

function logout() {
    FB.logout(function() {
        location.reload();
    });
}

function statusChangeCallback(response) {
    if (response.status === 'connected') {
        fetchUserProfileData(response);
        console.log("i will show the disconnect button");
        document.getElementById('disconnect').style.display = 'blocks';
        document.getElementById('connect').style.display = 'none';
    } else {
        console.log("i will show the connect button");
        document.getElementById('connect').style.display = 'blocks';
        document.getElementById('disconnect').style.display = 'none';
    }
}

function checkLoginState() {
    FB.getLoginStatus(function(response) {
        statusChangeCallback(response);
    });
}

async function fetchUserProfileData(response) {
    console.log('Welcome!  Fetching your information.... ');
    FB.api('/me', function(res) {
        console.log('Successful login for: ' + res.name, res);
        document.getElementById('status').innerHTML =
            'Welcome, ' + res.name + '!';
    });

    const userId = response.authResponse.userID;
    const accessToken = response.authResponse.accessToken;

    document.getElementById('loader').style.display = 'block';
    await getUserPages(userId, accessToken);
    document.getElementById('loader').style.display = 'none';
}

const userPages = [];

async function getUserPages(userID, userAccessToken, after = null) {
    const response = await sendFacebookRequest(after !== null ? after : `/${userID}/accounts?limit=3&access_token=${userAccessToken}`);

    await response.data.map( async (item, index) => {
        response.data[index].page_image_url = await getPagePictureURL(item);
    });

    userPages.push(...response.data);

    if(response.paging.next) {
        return getUserPages(null, null, response.paging.next);
    } else if(userID === null && userAccessToken === null) {
        await listAllPages(userPages);
    }
}

async function listAllPages(userPages = []) {
    if(userPages.length > 0) {
        document.getElementById('no_page_found').style.display = 'none';
    }

    let innerHtmlContent = '';
    userPages.map(async (item) => {
        let pageDom = pageDomTemplate;
        pageDom = pageDom.replace(`PAGE_IMAGE_URL`, await item.page_image_url);
        pageDom = pageDom.replace(`PAGE_ID`, item.id);
        pageDom = pageDom.replace(`PAGE_TITLE`, item.name);
        innerHtmlContent.concat(pageDom);
        document.getElementById('pages').innerHTML = document.getElementById('pages').innerHTML.concat(pageDom);
    });
}

async function getPagePictureURL(page) {
    return (await sendFacebookRequest(`/${page.id}/picture?redirect=0&access_token=${page.access_token}`)).data.url;
}

async function sendFacebookRequest(path, method = "GET", body = null, headers = { "Content-type": "application/json;" }) {
    try {
        const url = path.startsWith('http') ? path : `${FACEBOOK_GRAPH_URL}${path}`;
        return await (await fetch(url, {
            method,
            headers,
            mode: 'cors',
        })).json();
    } catch (e) {
        console.log('Error happened while sending request', e.message);
    }
}
