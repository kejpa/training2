import {
  getAccessToken,
  storeAccessToken,
} from '../stores/accessTokenStorage'

export default new (class APIService {
  apiBase: string

  /**
   * Klass för hantering av anrop mot API
   */
  constructor() {
    // Sätt basadressen för API:et
    this.apiBase = '/api/'
  }

  /**
   * Anropa API:et med GET-parametrar
   * @param {*} params Query-parametrar för anropet
   * @returns Promise-objekt
   */
  get(params: string): Promise<unknown> {
    let jwtToken = getAccessToken()
    return new Promise((resolve, reject) => {
      // Hämta data från endpointen
      fetch(this.apiBase + params, {
        headers: {'Authorization': 'Bearer ' + jwtToken},
        method: 'GET',
      })
        .then((response) => {
          if (response.status === 200) {
            resolve(response.json())
          } else if (response.status === 401) {
            fetch(this.apiBase + 'refresh')
              .then((second) => {
                if (second.ok) {
                  return second.json()
                } else {
                  throw second.json()
                }
              })
              .then((data) => {
                jwtToken = data.data.access_token
                storeAccessToken(jwtToken)
               fetch(this.apiBase + params, {
                  headers: {'Authorization': 'Bearer ' + jwtToken},
                  method: 'GET',
                }).then((response) => {
                  if (response.ok) {
                    resolve(response.json())
                  } else {
                    throw response.json()
                  }
                })
              })
              .catch((err) => {
                // Refreshtoken saknas
                reject(err)
              })
          } else {
            throw response.json()
          }
        })
        .catch((err) => {
          // Något gick fel
          reject(err)
        })
    })
  }

  /**
   * Anropar API:et med HEAD metoden
   * @param {*} params parametrar för anropet
   * @returns Promise-objekt
   */
  head(params: string): Promise<unknown> {
    return new Promise((resolve, reject) => {
      // Hämta data från endpointen
      fetch(this.apiBase + params, {
        method: 'HEAD',
        //        credentials: 'include'
      })
        .then((response) => {
          if (response.status === 200) {
            resolve(response.headers)
          } else {
            throw response.json()
          }
        })
        .catch((err) => {
          // Något gick fel
          reject(err)
        })
    })
  }

  /**
   * Anropar API:et med POST-parametrar
   * @param {*} params query-strängsparametrar
   * @param {*} object data som ska postas
   * @returns Promise-objekt
   */
  post(params: string, object = {}): Promise<unknown> {
    let jwtToken = getAccessToken()
    return new Promise((resolve, reject) => {
      // Skicka förfrågan till api:et
      fetch(this.apiBase + params, {
        method: 'POST',
        headers: {
          'Authorization': 'Bearer ' + jwtToken,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(object),
      })
        .then((response) => {
          if (response.status === 200) {
            resolve(response.json())
          } else if (response.status === 401) {
            fetch(this.apiBase + 'refresh')
              .then((response) => {
                if (response.ok) {
                  return response.json()
                } else {
                  throw response.json()
                }
              })
              .then((data) => {
                jwtToken = data.data.access_token
                storeAccessToken(jwtToken)
               fetch(this.apiBase + params, {
                  method: 'POST',
                  headers: {
                    'Authorization': 'Bearer ' + jwtToken,
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify(object),
                }).then((response) => {
                  if (response.ok) {
                    resolve(response.json())
                  } else {
                    throw response.json()
                  }
                })
              })
          } else {
            throw response.json()
          }
        })
        .catch(async function (err) {
          // Något gick fel
          const info = await err
          reject(info)
        })
    })
  }

  /**
   * Anropar API:et med PUT-parametrar
   * @param {*} params query-strängsparametrar
   * @param {*} object data som ska postas
   * @returns Promise-objekt
   */
  put(params: string, object = {}): Promise<unknown> {
    let jwtToken = getAccessToken()
    return new Promise((resolve, reject) => {
      // Skicka förfrågan till api:et
      fetch(this.apiBase + params, {
        method: 'PUT',
        headers: {
          'Authorization': 'Bearer ' + jwtToken,
          'Content-Type': 'application/json'
        },
        body: JSON.stringify(object),
      })
        .then((response) => {
          if (response.status === 200) {
            resolve(response.json())
          } else if (response.status === 401) {
            fetch(this.apiBase + 'refresh')
              .then((response) => {
                if (response.ok) {
                  return response.json()
                } else {
                  throw response.json()
                }
              })
              .then((data) => {
                jwtToken = data.data.access_token
                storeAccessToken(jwtToken)
                fetch(this.apiBase + params, {
                  method: 'PUT',
                  headers: {
                    'Authorization': 'Bearer ' + jwtToken,
                    'Content-Type': 'application/json'
                  },
                  body: JSON.stringify(object),
                }).then((response) => {
                  if (response.ok) {
                    resolve(response.json())
                  } else {
                    throw response.json()
                  }
                })
              })
          } else {
            throw response.json()
          }
        })
        .catch((err) => {
          // Något gick fel
          reject(err)
        })
    })
  }

  /**
   * Anropar API:et med DELETE-parametrar
   * @param {*} params query-strängsparametrar
   * @returns Promise-objekt
   */
  delete(params: string): Promise<unknown> {
    let jwtToken = getAccessToken()
    return new Promise((resolve, reject) => {
      // Skicka förfrågan till api:et
      fetch(this.apiBase + params, {
        method: 'DELETE',
        headers: {
          'Authorization': 'Bearer ' + jwtToken,
          'Content-Type': 'application/json'
        },
      })
        .then((response) => {
          if (response.status === 200) {
            resolve(response.json())
          } else if (response.status === 401) {
            fetch(this.apiBase + 'refresh')
              .then((response) => {
                if (response.ok) {
                  return response.json()
                } else {
                  throw response.json()
                }
              })
              .then((data) => {
                jwtToken = data.data.access_token
                storeAccessToken(jwtToken)
                fetch(this.apiBase + params, {
                  method: 'DELETE',
                  headers: {
                    'Authorization': 'Bearer ' + jwtToken,
                    'Content-Type': 'application/json'
                  },
                }).then((response) => {
                  if (response.ok) {
                    resolve(response.json())
                  } else {
                    throw response.json()
                  }
                })
              })
          } else {
            throw response.json()
          }
        })
        .catch((err) => {
          // Något gick fel
          reject(err)
        })
    })
  }
})()
