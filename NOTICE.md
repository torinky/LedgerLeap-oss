# NOTICE for LedgerLeap Project

This project, LedgerLeap, is licensed under the MIT License. For the full license text, please refer to the `LICENSE` file in the root directory of this project.

This project utilizes various third-party libraries and components, each governed by their respective licenses. The following is a summary of notable licenses and specific considerations for certain dependencies.

## Third-Party Licenses and Considerations

### 1. PHP Dependencies

Most PHP dependencies are licensed under permissive licenses such as MIT, BSD-3-Clause, or Apache-2.0, which are compatible with the MIT License.

**Specific Considerations:**

*   **`ezyang/htmlpurifier` (LGPL-2.1-or-later)**
    This library is licensed under the GNU Lesser General Public License (LGPL) version 2.1 or later. When used as a dynamically linked library (which is typical in Laravel projects via Composer), the LGPL generally allows the combined work to be distributed under a different license (like MIT), provided certain conditions are met. These conditions include: 
    *   The LGPL library itself is not modified, or if modified, the modifications are made available under LGPL.
    *   Users are able to replace the LGPL library with a modified version of their own.
    *   This project uses `ezyang/htmlpurifier` as a standard Composer dependency without modifications, which is generally compliant with LGPL conditions for dynamic linking.

*   **`nette/schema` and `nette/utils` (BSD-3-Clause, GPL-2.0-only, GPL-3.0-only)**
    These libraries are dual-licensed under BSD-3-Clause and GPL (versions 2.0-only and 3.0-only). This project utilizes these libraries under the **BSD-3-Clause** license, which is a permissive open-source license compatible with the MIT License. By choosing the BSD-3-Clause terms, this project avoids the copyleft obligations of the GPL.

### 2. JavaScript Dependencies

Most JavaScript dependencies are licensed under permissive licenses such as MIT or BSD-3-Clause, which are compatible with the MIT License.

**Specific Considerations:**

*   **`@fortawesome/fontawesome-free` (CC-BY-4.0 AND OFL-1.1 AND MIT)**
    Font Awesome Free is distributed under multiple licenses:
    *   **Fonts:** SIL Open Font License 1.1 (OFL-1.1)
    *   **CSS/SCSS/LESS files:** MIT License
    *   **Icons (graphics):** Creative Commons Attribution 4.0 International Public License (CC-BY-4.0)
    As required by the CC-BY-4.0 license for the icons, proper attribution must be given. This project acknowledges the use of Font Awesome. A general attribution can be found in the project's footer or similar visible location, for example: "Font Awesome by @fontawesome - https://fontawesome.com".

## General Disclaimer

This `NOTICE.md` file provides a summary of the licenses of third-party components used in this project. It is not exhaustive and does not replace the individual license texts of each component. Users are encouraged to review the license files of all included third-party software for complete details.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER LIABILITY, WHETHER IN AN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE SOFTWARE.
