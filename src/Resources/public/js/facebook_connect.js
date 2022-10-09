const FACEBOOK_APP_ID = document.getElementById('FACEBOOK_APP_ID').value;
const FACEBOOK_GRAPH_URL = document.getElementById('FACEBOOK_GRAPH_URL').value;
const FACEBOOK_GRAPH_VERSION = document.getElementById('FACEBOOK_GRAPH_VERSION').value;

let pageDomTemplate = `
<tr id="block">
    <td>
        <img class="ui avatar image mini page_image" id="page_image" src="PAGE_IMAGE_URL" onerror="this.onerror=null; this.src='/assets/shop/img/logo.png'" />
        <input style="display: none;" id="page_id" value="PAGE_ID"/>
        <span style="padding-left: 10px; font-size: large;" id="page_title"> PAGE_TITLE </span>
    </td>
    <td style="text-align: center">Connected</td>
    <td>
        <button class="ui green button fluid" id="connect">Connect</button>
        <button class="ui red button fluid" style="display: none" id="disconnect">Disconnect</button>
    </td>
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
            location.reload();
        } else {
            console.log('not logged in', response);
        }
    }, {
        scope: 'email,messages,messaging_postbacks',
        return_scopes: true
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
        document.getElementById('facebook_logout').style.display = 'blocks';
        document.getElementById('facebook_login').style.display = 'none';
    } else {
        console.log("i will show the connect button");
        document.getElementById('facebook_login').style.display = 'blocks';
        document.getElementById('disconnectfacebook_logout').style.display = 'none';
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
        document.getElementById('status').innerHTML =
            'Welcome, ' + res.name + '!';
    });

    const userId = response.authResponse.userID;
    const accessToken = response.authResponse.accessToken;

    document.getElementById('loader').style.display = 'block';
    const connected_pages_ids = (await $.ajax(`/admin/connected_pages`)).pages.map(item => item.page_id);
    await getUserPages(userId, accessToken, connected_pages_ids);
    document.getElementById('loader').style.display = 'none';
}

const userPages = [];

async function getUserPages(userID, userAccessToken, connected_pages_ids, after = null) {
    const response = await sendFacebookRequest(after !== null ? after : `/${userID}/accounts?limit=3&access_token=${userAccessToken}`);

    await response.data.map( async (item, index) => {
        response.data[index].connected = !!connected_pages_ids.includes(response.data[index].id);
        response.data[index].page_image_url = await getPagePictureURL(item);
    });

    userPages.push(...response.data);

    if(response.paging.next) {
        return getUserPages(null, null, connected_pages_ids, response.paging.next);
    } else if(userID === null && userAccessToken === null) {
        await listAllPages(userPages);
    }
}

async function listAllPages(userPages = []) {
    if(userPages.length > 0) {
        document.getElementById('no_page_found').style.display = 'none';
    }
    userPages.map(async (item) => {
        let pageDom = pageDomTemplate;
        pageDom = pageDom.replace(`PAGE_IMAGE_URL`, await item.page_image_url);
        pageDom = pageDom.replace(`PAGE_ID`, item.id);
        pageDom = pageDom.replace(`PAGE_TITLE`, item.name);
        var element =  $(pageDom);
        if(item.connected) {
            //show disconnect
            element.find('#connect').hide();
            element.find('#disconnect').show();
        } else {
            //show connect
            element.find('#connect').show();
            element.find('#disconnect').hide();
        }
        $('#pages').append(element);
    });
}

async function getPagePictureURL(page) {
    return (await sendFacebookRequest(`/${page.id}/picture?redirect=0&access_token=${page.access_token}`)).data.url;
}

async function sendFacebookRequest(path, method = "GET", body = null, headers = {}) {
    return await sendRequest(path.startsWith('http') ? path : `${FACEBOOK_GRAPH_URL}${path}`, method, body, headers);
}

async function sendRequest(url, method = "GET", body = null, headers = {}) {
    try {
        const options = {
            method,
            headers: {
                "Content-type": "application/json;",
                ...headers
            },
            mode: 'cors',
        }
        if(body !== null) {
            options.body = JSON.stringify(body);
        }
        return await (await fetch(url, options)).json();
    } catch (e) {
        console.log('Error happened while sending request', e.message);
    }
}

$('body').on('click', "#connect", async (event) => {
    const pageId = event.target.parentNode.parentNode.querySelector('#page_id').value;
    const page = getPageById(pageId);

    const response = await $.ajax({
        url: `/admin/bots/connect`,
        type: "POST",
        data: JSON.stringify(page),
        dataType: "json",
        contentType: 'application/json'
    });

    // hide connect and show disconnect for this page
    if(response.success) {
        $(event.target).parents('tr').find('#connect').hide();
        $(event.target).parents('tr').find('#disconnect').show();

        location.reload();
    }
});

$('body').on('click', "#disconnect", async (event) => {
    const pageId = $(event.target).parents('tr').find('#page_id').val();
    const page = getPageById(pageId);
    console.log('llllllllllllll', page);
    const response = await $.ajax({
        url: `/admin/bots/disconnect`,
        type: "POST",
        data: JSON.stringify(page),
        dataType: "json",
        contentType: 'application/json'
    });

    // hide disconnect and show connect for this page
    if(response.deleted) {
        $(event.target).parents('tr').find('#connect').show();
        $(event.target).parents('tr').find('#disconnect').hide();
    }
});

function getPageById(pageId) {
    return userPages.find(item => item.id === pageId );
}